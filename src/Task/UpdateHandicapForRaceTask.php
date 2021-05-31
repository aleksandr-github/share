<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use App\Enum\OrderEnum;
use App\Service\Algorithm\AlgorithmDebugLogger;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use Exception;
use mysqli;

class UpdateHandicapForRaceTask extends AbstractMySQLTask implements Task
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
        $this->positionPercentage = $_ENV['positionPercentage'] ?? $positionPercentage;
        $this->algorithm = $algorithm;
        $this->debugLogger = new AlgorithmDebugLogger();
    }

    public function run(Environment $environment): bool
    {
        $algStart = microtime(true);
        $mysqli = $this->initMultiSessionDatabase();

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data->race_id, __FILE__);

        /** TODO SEPARATE INTO TASKS!!!! */
        /** UPDATE RANK SECTION */
        $this->updateRankSectionForRace($this->data, $mysqli);
        /** UPDATE SECTIONAL AVG */
        $this->updateSectionalAVGForRace($this->data, $mysqli);
        /** UPDATE RATING */
        try {
            $this->updateRatingsAndH2HForRace($this->data, $mysqli);
        } catch (Exception $e) {
            $this->logger->log("Ratings for race " . $this->data->race_id . " not done. Cause: " . $e->getMessage(), __FILE__);
        }

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data->race_id . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return true;
    }

    /**
     * @param object $race
     * @param \mysqli $mysqli
     */
    private function updateRankSectionForRace(object $race, mysqli $mysqli)
    {
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
                                    OrderEnum::NORMAL
                                );

                                $this->debugLogger->varLog([
                                    'ACTION' => "AlgorithmStrategyInterface::generateRank()",
                                    'HORSE' => $horse->horse_id,
                                    'RACE' => $race->race_id,
                                    'RACE_DISTANCE' => $distance->racedist,
                                    'RANK' => $rank,
                                    'MIN_HANDICAP' => $row->minihandi,
                                    'ARRAY_OF_HANDICAP' => implode("@", $numsArray)
                                ]);

                                $q = "UPDATE `tbl_hist_results` 
                                        SET `rank`='$rank' 
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
        $arr = [];

        while ($arhorse = $get_array->fetch_object()) {
            $get_histar = $mysqli->query("SELECT MIN(handicap) as minihandi FROM `tbl_hist_results` WHERE `race_id`='$raceId' AND `race_distance`='$raceDistance' AND `horse_id`='$arhorse->horse_id'");
            while ($ahandi = $get_histar->fetch_object()) {
                $arr[] = $ahandi->minihandi;
            }
        }

        return $arr;
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
                            // Describe how AVGSECTIONAL is calulated...
                            // OMG
                            $avgSectional = $this->algorithm->generateAVGSectional(
                                $row->secavg,
                                $numsArray,
                                OrderEnum::NORMAL
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
        $get_array = $mysqli->query("SELECT DISTINCT `horse_id` FROM `tbl_hist_results` WHERE `race_id`='$raceid' AND `race_distance`='$racedis'");
        $arr = array();
        while ($arhorse = $get_array->fetch_object()) {
            $get_histar = $mysqli->query("SELECT MAX(avgsec) AS secavg FROM `tbl_hist_results` WHERE `race_id`='$raceid' AND `race_distance`='$racedis' AND `horse_id`='$arhorse->horse_id'");
            while ($asec = $get_histar->fetch_object()) {
                $arr[] = $asec->secavg;
            }
        }

        return $arr;
    }

    /**
     * @throws Exception
     */
    private function updateRatingsAndH2HForRace(object $race, mysqli $mysqli)
    {
        $raceId = $race->race_id;
        // Rating
        if ($raceId) {
            $q = "SELECT * FROM `tbl_hist_results` WHERE `rating`='0' AND `race_id`='$raceId'";
        } else {
            throw new Exception("No race ID found!");
        }

        // THIS IS TRUE CALCULATION OF RATING
        $results = $mysqli->query($q);
        if ($results->num_rows > 0) {
            while ($row = $results->fetch_object()) {
                if ($row->avgsectional != "0" || $row->rank != "0") {
                    // how avg sectional and rank are calculated?!
                    $ratePos = $row->avgsectional + $row->rank;
                    // modify by Jfrost
                    $h2hPoint = (float)$this->algorithm->getH2HPoint($row->horse_h2h);
                    $rating = $ratePos + $h2hPoint;
                    $q = "UPDATE `tbl_hist_results` 
                                 SET `rating` = '$rating' , `temp_h2h` = " . $row->horse_h2h . "
                                 WHERE `hist_id` = '$row->hist_id';";

                    $this->debugLogger->varLog([
                        'ACTION' => "AlgorithmStrategyInterface::getH2HPoint()",
                        'HORSE' => $row->horse_id,
                        'RACE' => $race->race_id,
                        'RACE_DISTANCE' => $row->race_distance,
                        'HISTID' => $row->hist_id,
                        'RATING' => $rating,
                        "H2HPOINT" => $h2hPoint,
                        "H2HHORSE" => $row->horse_h2h,
                        "RATEPOS" => $ratePos,
                        "AVGSECTIONAL" => $row->avgsectional,
                        "ROWRANK" => $row->rank
                    ]);

                    $mysqli->query($q);
                }
            }
        }
    }
}