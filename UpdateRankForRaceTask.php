<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use App\Enum\OrderEnum;
use App\Service\Algorithm\AlgorithmDebugLogger;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use Exception;
use mysqli;

class UpdateRankForRaceTask extends AbstractMySQLTask implements Task
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

        $this->updateRankForRace($this->data, $mysqli);

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data->race_id . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return true;
    }

    /**
     * @param object $race
     * @param \mysqli $mysqli
     */
    private function updateRankForRace(object $race, mysqli $mysqli)
    {
        //update rank to calc_rank in tbl_hist_results
        $query = "UPDATE tbl_hist_results SET  cal_rank = rank";
        $mysqli->query($query);

        //getting rank array from hist_table
        $horseQuery = "SELECT horse_id  FROM `tbl_hist_results` GROUP BY horse_id ORDER BY horse_id";
        $horseIDs = $mysqli->query($horseQuery);
        while ($horseID = $horseIDs->fetch_object()) {
            $arrayRankByHorse = $this->getArrayOfRank(
                $race->race_id,
                $horseID->horse_id,
                $mysqli
            );//For one horse
            $arrayRankByDistance = implode("@", $arrayRankByHorse);//by distance
            $arrayAvgRankByDistance[] = $arrayRankByDistance;
        }

        $arrayAvgRankByHorse = implode("&", array_filter($arrayAvgRankByDistance));//by horse

        $horsesCount = $mysqli->query("SELECT * FROM `tbl_temp_hraces` WHERE `race_id`='" . $this->data->race_id . "' AND `horse_fxodds`!='0'")->num_rows;

        $qDistance = "SELECT DISTINCT CAST(race_distance AS UNSIGNED) AS racedist 
                          FROM tbl_hist_results 
                          WHERE `race_id`='$race->race_id' 
                          ORDER by racedist ASC";
        $distances = $mysqli->query($qDistance);

        while ($distance = $distances->fetch_object()) {
            $numsArray = $this->getArrayOfHandicap(
                $race->race_id,
                $distance->racedist,
                $mysqli
            );
            $namesArray = $this->getNameArrayOfHandicap(
                $race->race_id,
                $distance->racedist,
                $mysqli
            );


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

                if (isset($odds->horse_fxodds) && $odds->horse_fxodds != "0") {
                    $handicapResults = $mysqli->query(
                        "SELECT MIN(handicap) as minihandi 
                             FROM `tbl_hist_results` 
                             WHERE `race_id`='$race->race_id' 
                             AND `race_distance`='$distance->racedist' 
                             AND `horse_id`='$horse->horse_id'"
                    );

                    while ($row = $handicapResults->fetch_object()) {
                        if ($horsesCount > 0) {
                            $per = ($cnt / $horsesCount) * 100;

                            if ($per > $this->positionPercentage) {
                                $rank = $this->algorithm->generateRank(
                                    $row->minihandi,
                                    $numsArray,
                                    OrderEnum::REVERSED
                                );

                                $this->debugLogger->varLog([
                                    'ACTION' => "AlgorithmStrategyInterface::generateRank()",
                                    'HORSE' => $horse->horse_id,
                                    'RACE' => $race->race_id,
                                    'RACE_DISTANCE' => $distance->racedist,
                                    'RANK' => $rank,
                                    'MIN_HANDICAP' => $row->minihandi,
                                    'ARRAY_OF_HANDICAP' => implode("@", $numsArray),
                                    'NAMEARRAY_OF_HANDICAP' => implode("@", $namesArray),
                                    'CALCULATION_OF_AVERAGE_RANK' => $arrayAvgRankByHorse
                                ]);

                                $q = "UPDATE `tbl_hist_results` 
                                        SET `rank`='$rank' 
                                        WHERE `race_id`='$race->race_id' 
                                        AND `race_distance`= '$distance->racedist' 
                                        AND `horse_id`='$horse->horse_id';";

                                $mysqli->query($q);

                                $q = "UPDATE `tbl_races` 
                                          SET `rank_status`='1' 
                                          WHERE `race_id`=".$this->data->race_id;
                                $mysqli->query($q);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $raceId
     * @param $raceDistance
     * @param $mysqli
     * @return array
     */
    protected function getArrayOfHandicap($raceId, $raceDistance, $mysqli): array
    {
        $get_array = $mysqli->query("SELECT DISTINCT `horse_id` FROM `tbl_hist_results` WHERE `race_id`='$raceId' AND `race_distance`='$raceDistance'");
        $arr = array();

        while ($arhorse = $get_array->fetch_object()) {
            $get_histar = $mysqli->query("SELECT MIN(handicap) as minihandi FROM `tbl_hist_results` WHERE `race_id`='$raceId' AND `race_distance`='$raceDistance' AND `horse_id`='$arhorse->horse_id'");
            while ($ahandi = $get_histar->fetch_object()) {
                $arr[] = $ahandi->minihandi;
            }
        }

        return $arr;
    }

    protected function getNameArrayOfHandicap($raceId, $raceDistance, $mysqli): array
    {
        $get_array = $mysqli->query("SELECT DISTINCT `horse_id` FROM `tbl_hist_results` WHERE `race_id`='$raceId' AND `race_distance`='$raceDistance'");
        $arr = [];

        while ($arhorse = $get_array->fetch_object()) {
            $query = "SELECT horse_name FROM `tbl_horses` WHERE `horse_id`='$arhorse->horse_id'";
            $get_horse = $mysqli->query($query);
            while ($aname = $get_horse->fetch_object()) {
                $arr[] = $aname->horse_name;
            }
        }

        return $arr;
    }

    protected function getArrayOfRank($raceID, $horseID, $mysqli): array
    {
        $query = "SELECT * FROM `tbl_temp_hraces` WHERE `race_id`='$raceID' AND `horse_fxodds`!='0'";
        //total horse count
        $horsesCount = $mysqli->query($query)->num_rows;
        $arr = array();
        $query = "SELECT hist.*,  hs.horse_name FROM `tbl_hist_results` AS hist INNER JOIN tbl_horses AS hs ON hs.horse_id=hist.horse_id WHERE hist.horse_id='$horseID' AND hist.race_id='$raceID'";
        $get_horse = $mysqli->query($query);
        if ($get_horse->num_rows > 0) {
            while ($result = $get_horse->fetch_object()) {
                //horse array count per race id and distance
                $numsArray = $this->getArrayOfHandicap($raceID, $result->race_distance, $mysqli);
                $cnt = count($numsArray);
                $rank = 0;
                if ($horsesCount > 0) {
                    $per = ($cnt / $horsesCount) * 100;

                    if ($per > $this->positionPercentage) {
                        $rank = $result->cal_rank;
                    }
                    else{
                        $rank = 0;
                    }
                }
                $arr[] = $raceID.'#'.$horseID.'#'.$result->horse_name.'#'.$result->race_distance.'#'.$result->race_time.'#'.$rank.'#'.$result->horse_position;
            }
        }

        return $arr;
    }



}