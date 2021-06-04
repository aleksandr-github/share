<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use App\Enum\OrderEnum;
use App\Service\Algorithm\AlgorithmDebugLogger;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use mysqli;

class UpdateSectionalAVGForRaceTask extends AbstractMySQLTask implements Task
{
    /**
     * @var object
     */
    protected $data;

    /**
     * @var AlgorithmStrategyInterface
     */
    protected $algorithm;

    protected $debugLogger;

    protected $positionPercentage;

    public function __construct(AlgorithmStrategyInterface $algorithm, object $race, float $positionPercentage)
    {
        parent::__construct();

        $this->data = $race;
        $this->positionPercentage = $positionPercentage;
        $this->algorithm = $algorithm;
        $this->debugLogger = new AlgorithmDebugLogger();
    }

    public function run(Environment $environment): bool
    {
        $algStart = microtime(true);
        $mysqli = $this->initMultiSessionDatabase();

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data->race_id, __FILE__);

        $this->updateSectionalAVGForRace($this->data, $mysqli);

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data->race_id . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return true;
    }

    private function updateSectionalAVGForRace(object $race, mysqli $mysqli)
    {
        $horsesCount = $mysqli->query("SELECT * FROM `tbl_temp_hraces` WHERE `race_id`='" . $this->data->race_id . "' AND `horse_fxodds`!='0'")->num_rows;

        $qDistance = "SELECT DISTINCT CAST(race_distance AS UNSIGNED) AS racedist 
                          FROM tbl_hist_results 
                          WHERE `race_id`='$race->race_id' 
                          ORDER by racedist ASC";
        $distances = $mysqli->query($qDistance);

        while ($distance = $distances->fetch_object()) {
            $numsArray = $this->getArrayOfAVGSectional($race->race_id, $distance->racedist, $mysqli);
            $cnt = count($numsArray);

            $horsesHistResult = $mysqli->query(
                "SELECT DISTINCT `horse_id` 
                     FROM `tbl_hist_results` 
                     WHERE `race_id`='$race->race_id' 
                     AND `race_distance`='$distance->racedist'"
            );

            while ($horse = $horsesHistResult->fetch_object()) {
                $oddsResult = $mysqli->query(
                    "SELECT * FROM `tbl_temp_hraces` 
                         WHERE `race_id`='$race->race_id' 
                         AND `horse_id`='$horse->horse_id'"
                );
                if ($oddsResult->num_rows === 0) continue;

                $odds = $oddsResult->fetch_object();
                if ($odds->horse_fxodds != "0") {
                    $handicapResults = $mysqli->query(
                        "SELECT MAX(avgsec) AS secavg 
                             FROM `tbl_hist_results` 
                             WHERE `race_id`='$race->race_id' 
                             AND `race_distance`='$distance->racedist' 
                             AND `horse_id`='$horse->horse_id'"
                    );

                    while ($row = $handicapResults->fetch_object()) {
                        $per = ($cnt / $horsesCount) * 100;

                        if ($per > $this->positionPercentage) {
                            $avgSectional = $this->algorithm->generateAVGSectional(
                                $row->secavg,
                                $numsArray,
                                OrderEnum::REVERSED
                            );

                            $this->debugLogger->varLog([
                                'ACTION' => "AlgorithmStrategyInterface::generateAVGSectional()",
                                'HORSE' => $horse->horse_id,
                                'RACE' => $race->race_id,
                                'RACE_DISTANCE' => $distance->racedist,
                                'MAXSECAVG' => $row->secavg,
                                'AVGSECTIONAL' => $avgSectional,
                                'AVGSECTIONALARRAY' => implode("@", $numsArray)
                            ]);

                            $q = "UPDATE `tbl_hist_results` 
                                     SET `avgsectional`='$avgSectional' 
                                     WHERE `race_id`='$race->race_id' 
                                     AND `race_distance`= '$distance->racedist' 
                                     AND `horse_id`='$horse->horse_id';";
                            $mysqli->query($q);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $raceid
     * @param $racedis
     * @param $mysqli
     * @return array
     */
    protected function getArrayOfAVGSectional($raceid, $racedis, $mysqli): array
    {
        $arrayOfHorseIds = $mysqli->query("SELECT DISTINCT `horse_id` FROM `tbl_hist_results` WHERE `race_id`='$raceid' AND `race_distance`='$racedis'");
        $arr = [];
        while ($arhorse = $arrayOfHorseIds->fetch_object()) {
            $get_histar = $mysqli->query("SELECT MAX(avgsec) AS secavg FROM `tbl_hist_results` WHERE `race_id`='$raceid' AND `race_distance`='$racedis' AND `horse_id`='$arhorse->horse_id'");
            while ($asec = $get_histar->fetch_object()) {
                $arr[] = $asec->secavg;
            }
        }

        return $arr;
    }
}