<?php

namespace App\Service;

use Amp\Loop;
use Amp\Parallel\Worker\DefaultPool;
use App\DataTransformer\HorsesForRacesTransformer;
use App\DataTransformer\RacesForMeetingsTransformer;
use App\Enum\WorkersPurposeEnum;
use App\Helper\ArrayObjectHelper;
use App\Helper\HorseSlugHelper;
use App\Model\App\HistoricResult;
use App\Task\GetHorsesForRacesTask;
use App\Task\GetRacesForMeetingsTask;
use App\Task\GetRecordsForHorsesInRacesTask;
use App\Task\GenerateHandicapForHistoricResultTask;
use App\Task\ResetHandicapTask;
use App\Task\ResetRankTask;
use App\Task\ResetRatingTask;
use App\Task\ResetSectionalTask;
use App\Task\SaveHorsesTask;
use App\Task\SaveMeetingsTask;
use App\Task\SaveRacesTask;
use App\Task\SaveRecordsTask;
use App\Task\UpdateDistanceRankForRaceTask;
use App\Task\UpdateRatingForRaceTask;
use App\Task\UpdateRankForRaceTask;
use App\Task\UpdateSectionalAVGForRaceTask;
use App\Task\UpdateSectionalTask;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;
use function Amp\call as callAmp;
use function Amp\Promise\all as waitAll;
use function Amp\Promise\first as firstPromise;

class TasksService
{
    const DEFAULT_WORKERS = 4;
    const NUMBER_OF_DEFAULT_POOLS = 16;

    protected $logger;
    protected $mysqliWorkersNumber;
    protected $httpWorkersNumber;
    protected $dbConnector;
    protected $algorithmContext;

    /**
     * @var \App\Service\Algorithm\Strategy\AlgorithmStrategyInterface
     */
    protected $algorithm;

    public function __construct(ContainerInterface $container, $mysqliWorkersNumber, $httpWorkersNumber)
    {
        $this->logger = new PrettyLogger(__FILE__, "main_log.txt");
        $this->dbConnector = new DBConnector();
        $this->mysqliWorkersNumber = intval($mysqliWorkersNumber);
        $this->httpWorkersNumber = intval($httpWorkersNumber);
        $this->algorithmContext = $container->get('algorithmContext');
        $this->algorithm = $this->algorithmContext->getAlgorithm();
    }

    /**
     * @param ArrayCollection $horses
     * @return mixed
     * @throws Throwable
     *
     * @uses \mysqli
     */
    public function saveHorses(ArrayCollection $horses)
    {
        $results = [];
        $tasks = [];

        // build tasks
        foreach ($horses->toArray() as $horseArrayCollection) {
            foreach ($horseArrayCollection->toArray() as $horse) {
                $task = new SaveHorsesTask($this->algorithm, $horse);
                $uniqueId = $horse["field_id"] . $horse["id"] . random_int(0, 99999);
                $tasks[$uniqueId] = $task;
            }
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return $results;
    }

    /**
     * @param ArrayCollection $meetingsForDateRange
     * @return mixed
     * @throws Throwable
     *
     * @uses \mysqli
     */
    public function saveMeetings(ArrayCollection $meetingsForDateRange)
    {
        $results = [];
        $tasks = [];

        // build tasks
        foreach ($meetingsForDateRange->toArray() as $meeting) {
            $task = new SaveMeetingsTask($meeting);
            $tasks[$meeting["url"]] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return $results;
    }

    /**
     * @param ArrayCollection $races
     * @return mixed
     * @throws Throwable
     *
     * @uses \mysqli
     */
    public function saveRaces(ArrayCollection $races)
    {
        $results = [];
        $tasks = [];

        // build tasks
        foreach ($races->toArray() as $race) {
            $task = new SaveRacesTask($race);
            $tasks[$race["url"]] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return $results;
    }

    /**
     * @param $records
     * @return mixed
     * @uses \mysqli
     */
    public function saveRecords($records, array $racesOldIdMappingsArray, array $horseIdSlugNameMappingsArray)
    {
        $results = [];
        $tasks = [];

        foreach ($records as $recordsForHorseArray) {
            foreach ($recordsForHorseArray as $recordsArray) {
                if (count($recordsArray) > 0) {
                    foreach ($recordsArray as $record) {
                        $record['race_id'] = $racesOldIdMappingsArray[$record['race_old_id']];
                        $record['horse_true_id'] = $horseIdSlugNameMappingsArray[
                            HorseSlugHelper::generate($record['name'])
                        ];

                        $sql = "SELECT horse_fxodds, horse_h2h, horse_num FROM `tbl_temp_hraces` WHERE horse_id =".$record['horse_true_id']." AND race_id = ".$record['race_id']." LIMIT 1";
                        $stmt = $this->dbConnector->getDbConnection()->query($sql);
                        while ($result = $stmt->fetch_object()) {
                            $record['mysql_horse_fxodds'] = $result->horse_fxodds;
                            $record['mysql_horse_h2h'] = $result->horse_h2h;
                            $record['mysql_horse_num'] = $result->horse_num;
                        }

                        $task = new SaveRecordsTask(
                            $this->algorithm,
                            $record
                        );
                        $tasks[] = $task;
                    }
                }
            }
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return $this->dbConnector->multiQueryInsertIDs(implode("", $results));
    }


    /**
     * @param array $histResults
     * @param float $timerHandicapMultiplier
     * @param float $handicapModifier
     * @return bool
     *
     * @uses \mysqli
     */
    public function generateHandicapForHistoricResults(array $histResults, float $timerHandicapMultiplier, float $handicapModifier): bool
    {
        $results = [];
        $tasks = [];

        foreach ($histResults as $histResult) {
            $histResultObject = ArrayObjectHelper::covertToObject($histResult);
            $historicResult = new HistoricResult($histResultObject);
            $raceDetails = $this->dbConnector->getRaceDetails($historicResult->getRaceId());

            $task = new GenerateHandicapForHistoricResultTask(
                $this->algorithm,
                $historicResult,
                $timerHandicapMultiplier,
                $handicapModifier,
                $raceDetails->getRaceDistance()
            );
            $tasks[] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI); // test more workers

        // multi
        // return $this->dbConnector->multiQueryInsertIDs(implode("", $results));
        return true;
    }

    /**
     * @param array $races
     * @return bool
     * @throws Throwable
     *
     * @uses \mysqli
     */
    public function updateRankForRaces(array $races, float $positionPercentage): bool
    {
        $results = [];
        $tasks = [];

        foreach ($races as $race) {
            $raceObject = ArrayObjectHelper::covertToObject($race);
            $task = new UpdateRankForRaceTask(
                $this->algorithm,
                $raceObject,
                $positionPercentage
            );
            $tasks[] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI); // test more workers

        return true;
    }

    /**
     * @param array $races
     * @return bool
     * @throws Throwable
     *
     * @uses \mysqli
     */
    public function updateRatingForRaces(array $races, float $positionPercentage): bool
    {
        $results = [];
        $tasks = [];

        foreach ($races as $race) {
            $raceObject = ArrayObjectHelper::covertToObject($race);
            $task = new UpdateRatingForRaceTask(
                $this->algorithm,
                $raceObject,
                $positionPercentage
            );
            $tasks[] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI); // test more workers

        return true;
    }

    /**
     * @param array $races
     * @param float $positionPercentage
     * @return bool
     * @uses \mysqli
     */
    public function updateSectionalAVGForRaces(array $races, float $positionPercentage): bool
    {
        $results = [];
        $tasks = [];

        foreach ($races as $race) {
            $raceObject = ArrayObjectHelper::covertToObject($race);
            $task = new UpdateSectionalAVGForRaceTask(
                $this->algorithm,
                $raceObject,
                $positionPercentage
            );
            $tasks[] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI); // test more workers

        return true;
    }

    /**
     * @param array $races
     * @param array $raceDistanceArray
     * @param float|null $positionPercentage
     * @return bool
     * @deprecated Please use TasksService::updateRankForRaces()
     *
     * @uses \mysqli
     */
    public function updateDistanceRankForRaces(array $races, array $raceDistanceArray, ?float $positionPercentage): bool
    {
        $results = [];
        $tasks = [];

        foreach ($races as $race) {
            $raceObject = ArrayObjectHelper::covertToObject($race);
            $task = new UpdateDistanceRankForRaceTask(
                $this->algorithm,
                $raceObject,
                $raceDistanceArray[$raceObject->race_id],
                $positionPercentage
            );
            $tasks[] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI); // test more workers

        return true;
    }

    /**
     * @param array $meetings
     * @return ArrayCollection
     * @throws Throwable
     *
     * @uses \Amp\Http\Client\HttpClientBuilder
     */
    public function getRacesForMeetings(array $meetings): ArrayCollection
    {
        $results = [];
        $tasks = [];

        // build tasks
        foreach ($meetings as $meeting) {
            $task = new GetRacesForMeetingsTask($meeting);
            $tasks[$meeting["meeting_url"]] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::HTTP);

        return (new RacesForMeetingsTransformer($results))->transform();
    }

    /**
     * @param array $races
     * @return ArrayCollection
     * @throws Throwable
     *
     * @uses \Amp\Http\Client\HttpClientBuilder
     */
    public function getHorsesForRaces(array $races): ArrayCollection
    {
        $results = [];
        $tasks = [];

        // build tasks
        foreach ($races as $race) {
            $task = new GetHorsesForRacesTask($race);
            $tasks[$race["race_url"]] = $task;
        }

        $this->runTasks($tasks, $results, WorkersPurposeEnum::HTTP);

        return (new HorsesForRacesTransformer($results))->transform();
    }

    /**
     * @param ArrayCollection $horsesInRacesArrayCollection
     * @param bool $chunked
     * @return array
     *
     * @uses \Amp\Http\Client\HttpClientBuilder
     */
    public function getRecordsForHorsesInRaces(ArrayCollection $horsesInRacesArrayCollection, bool $chunked = true): array
    {
        $results = [];
        $tasks = [];

        // chunked is somewhat faster but requires testing
        if ($chunked) {
            $resultingPromiseArray = [];
            $workersLimit = 1;
            $splicedHorsesInRacesArray = array_chunk($horsesInRacesArrayCollection->toArray(), $workersLimit, true);
            foreach ($splicedHorsesInRacesArray as $chunkKey => $item) {
                $horseRaceResults = [];
                $timeNow = microtime(true);
                $this->logger->log("Starting chunk " . $chunkKey . " out of " . count($splicedHorsesInRacesArray) . " in records... ");
                foreach ($item as $raceKeyId => $horseRaceResults) {
                    foreach ($horseRaceResults as $horseRaceResult) {
                        $task = new GetRecordsForHorsesInRacesTask($horseRaceResult, $raceKeyId);
                        $tasks[$horseRaceResult["race_id"] . $horseRaceResult["id"]] = $task;
                    }
                }

                $this->runTasks($tasks, $results, WorkersPurposeEnum::HTTP);

                $resultingPromiseArray[] = $results;

                $timeDiff = microtime(true) - $timeNow;
                $this->logger->log("Chunk " . $chunkKey . " containing " . count($horseRaceResults) ." entries finished in " . number_format($timeDiff, 2) . " seconds.");
            }

            // hard coded transformer TODO resolve to Transformer class
            $promisesFinal = [];
            foreach ($resultingPromiseArray as $item) {
                foreach ($item as $promiseFinal) {
                    $promisesFinal[] = $promiseFinal;
                }
            }

            return $promisesFinal;
        } else {
            foreach ($horsesInRacesArrayCollection->toArray() as $raceKeyId => $horseRaceResults) {
                foreach ($horseRaceResults as $horseRaceResult) {
                    $task = new GetRecordsForHorsesInRacesTask($horseRaceResult, $raceKeyId);
                    $tasks[$horseRaceResult["race_id"] . $horseRaceResult["id"]] = $task;
                }
            }

            $this->runTasks($tasks, $results, WorkersPurposeEnum::HTTP);

            return $results;
        }
    }

    public function resetHandicap(): bool
    {
        $results = [];
        $tasks[] = new ResetHandicapTask();

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return true;
    }

    public function resetSectional(): bool
    {
        $results = [];
        $tasks[] = new ResetSectionalTask();

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return true;
    }

    public function resetRank(): bool
    {
        $results = [];
        $tasks[] = new ResetRankTask();

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return true;
    }

    public function resetRating(): bool
    {
        $results = [];
        $tasks[] = new ResetRatingTask();

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return true;
    }

    public function updateSectional(): bool
    {
        $results = [];
        $tasks[] = new UpdateSectionalTask();

        $this->runTasks($tasks, $results, WorkersPurposeEnum::MYSQLI);

        return true;
    }
    /**
     * @param array $tasks
     * @param array $results
     * @param int|null $workersPurpose
     */
    protected function runTasks(array $tasks, array &$results, int $workersPurpose = null)
    {
        switch ($workersPurpose) {
            case WorkersPurposeEnum::HTTP:
                $workersNumber = $this->httpWorkersNumber;
                break;
            case WorkersPurposeEnum::MYSQLI:
                $workersNumber = $this->mysqliWorkersNumber;
                break;
            default:
                $workersNumber = self::DEFAULT_WORKERS;
                break;
        }
        // failsafe
        if ($workersNumber == null) {
            $workersNumber = self::DEFAULT_WORKERS;
        }

        Loop::run(function () use (&$results, $tasks, $workersNumber) {
            $allCoroutines = [];
            $loopRoutines = [];
            $pool = new DefaultPool($workersNumber);
            foreach ($tasks as $index => $task) {
                $coroutine = callAmp(function () use ($pool, $task) {
                    return yield $pool->enqueue($task);
                });
                $loopRoutines[] = $coroutine;
                $allCoroutines[] = $coroutine;
                if ($pool->getWorkerCount() >= $workersNumber && $pool->getIdleWorkerCount() === 0) {
                    yield firstPromise($loopRoutines);
                    $loopRoutines = [];
                }
            }
            $results = yield waitAll($allCoroutines);

            return yield $pool->shutdown();
        });
    }
}