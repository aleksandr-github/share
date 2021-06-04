<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use App\Enum\OrderEnum;
use App\Service\Algorithm\AlgorithmDebugLogger;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use Exception;

class UpdateDistanceRankForRaceTask extends AbstractMySQLTask implements Task
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
    protected $raceDistance;

    public function  __construct(AlgorithmStrategyInterface $algorithm, object $race, $raceDistance, float $positionPercentage)
    {
        parent::__construct();

        $this->algorithm = $algorithm;
        $this->data = $race;
        $this->raceDistance = $raceDistance;
        $this->positionPercentage = $positionPercentage;
        $this->debugLogger = new AlgorithmDebugLogger();
    }

    /**
     * @param Environment $environment
     * @return bool
     * @throws Exception
     */
    public function run(Environment $environment): bool
    {
        $algStart = microtime(true);
        $mysqli = $this->initMultiSessionDatabase();

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data->race_id, __FILE__);

        $horsesCount = $mysqli->query("SELECT * FROM `tbl_temp_hraces` WHERE `race_id`='".$this->data->race_id."' AND `horse_fxodds`!='0'")->num_rows;
        $distancesResult = $mysqli->query(
            "SELECT DISTINCT CAST(race_distance AS UNSIGNED) AS racedist 
                 FROM tbl_hist_results 
                 WHERE `race_id`=".$this->data->race_id." 
                 AND `race_distance`='".$this->raceDistance."' 
                 ORDER by racedist ASC"
        );

        while ($distance = $distancesResult->fetch_object()) {
            $numsArray = $this->getArrayOfHandicap($this->data->race_id, $distance->racedist, $mysqli);
            $cnt = count($numsArray);
            $horsesHistResult = $mysqli->query(
                "SELECT DISTINCT `horse_id` 
                     FROM `tbl_hist_results` 
                     WHERE `race_id`=".$this->data->race_id." 
                     AND `race_distance`='$distance->racedist'"
            );

            while ($horseHist = $horsesHistResult->fetch_object()) {
                $odds = $mysqli->query(
                    "SELECT * FROM `tbl_temp_hraces` 
                         WHERE `race_id`=".$this->data->race_id." 
                         AND `horse_id`='".$horseHist->horse_id."'"
                );
                $oddsResult = $odds->fetch_object();

                if (isset($oddsResult->horse_fxodds)
                    && $oddsResult->horse_fxodds != "0") {
                    $handicapResult = $mysqli->query(
                        "SELECT MIN(handicap) as minihandi 
                             FROM `tbl_hist_results` 
                             WHERE `race_id`=".$this->data->race_id." 
                             AND `race_distance`='$distance->racedist' 
                             AND `horse_id`='$horseHist->horse_id'"
                    );

                    while ($handicap = $handicapResult->fetch_object()) {
                        if ($horsesCount > 0) {
                            $per = ($cnt / $horsesCount) * 100;

                            if ($per > $this->positionPercentage) {
                                // get rank
                                $rank = $this->algorithm->distanceNewRank(
                                    $handicap->minihandi,
                                    $numsArray,
                                    OrderEnum::REVERSED
                                );

                                $updateQuery = "UPDATE `tbl_hist_results` 
                                         SET `rank`='$rank' 
                                         WHERE `race_id`=".$this->data->race_id." 
                                         AND `race_distance`= '$distance->racedist' 
                                         AND `horse_id`='$horseHist->horse_id';";
                                $mysqli->query($updateQuery);

                                $this->debugLogger->varLog([
                                    'ACTION' => "AlgorithmStrategyInterface::distanceNewRank()",
                                    'HORSE' => $horseHist->horse_id,
                                    'RACE' => $this->data->race_id,
                                    'RACE_DISTANCE' => $distance->racedist,
                                    'RANK' => $rank,
                                    'MIN_HANDICAP' => $handicap->minihandi
                                ]);

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

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data->race_id . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return true;
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

        while ($arhorse = $get_array->fetch_object())
        {
            $get_histar = $mysqli->query("SELECT MIN(handicap) as minihandi FROM `tbl_hist_results` WHERE `race_id`='$raceId' AND `race_distance`='$raceDistance' AND `horse_id`='$arhorse->horse_id'");
            while ($ahandi = $get_histar->fetch_object())
            {
                $arr[] = $ahandi->minihandi;
            }
        }

        return $arr;
    }
}