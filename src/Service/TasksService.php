<?php

namespace App\Service;

use Amp\Loop;
use Amp\Parallel\Worker\DefaultPool;
use App\DataTransformer\HorsesForRacesTransformer;
use App\DataTransformer\RacesForMeetingsTransformer;
use App\Enum\WorkersPurposeEnum;
use App\Helper\ArrayObjectHelper;
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
use App\Task\UpdateRankForRaceTask;
use App\Task\UpdateHandicapForRaceTask;
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
        foreach ($horses->toArray() as $raceIdKey => $raceArrayCollection) {
            foreach ($raceArrayCollection->toArray() as $horse) {
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
     * @param ArrayCollection $races
     * @return mixed
     * @throws Throwable
     *
     * @uses \mysqli
     */
    public function saveRecords($records)
    {
        $results = [];
        $tasks = [];

        foreach ($records as $recordsForHorseArray) {
            foreach ($recordsForHorseArray as $recordsArray) {
                if (count($recordsArray) > 0) {
                    foreach ($recordsArray as $record) {
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

            $task = new GenerateHandicapForHistoricResultTask(
                $this->algorithm,
                $historicResult,
                $timerHandicapMultiplier,
                $handicapModifier
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
    public function updateHandicapTimeForRaces(array $races, float $positionPercentage): bool
    {
        $results = [];
        $tasks = [];

        foreach ($races as $race) {
            $raceObject = ArrayObjectHelper::covertToObject($race);
            $task = new UpdateHandicapForRaceTask(
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
     * @return bool
     * @throws Throwable
     *
     * @uses \mysqli
     */
    public function updateRankForRaces(array $races, array $raceDistanceArray, ?float $positionPercentage): bool
    {
        $results = [];
        $tasks = [];

        foreach ($races as $race) {
            $raceObject = ArrayObjectHelper::covertToObject($race);
            $task = new UpdateRankForRaceTask(
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
     * @param array $meetings
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