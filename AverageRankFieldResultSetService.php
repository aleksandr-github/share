<?php

namespace App\Service\Homepage;

use App\Helper\OddsHelper;
use App\Helper\ProfitLossCalculationHelper;
use App\Model\App\HorseDataModel;
use App\Model\AverageRankFieldResultSet;
use App\Service\DBConnector;
use Symfony\Component\HttpFoundation\Request;

class AverageRankFieldResultSetService
{
    /**
     * @var DBConnector
     */
    protected $dbConnector;

    public function __construct(DBConnector $dbConnector)
    {
        $this->dbConnector = $dbConnector;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param bool $oddsEnabled
     * @return \App\Model\AverageRankFieldResultSet
     */
    public function generateAvgRankFieldSelectorResultSet(Request $request, bool $oddsEnabled, int $limit = null, int $offset = null, int $selector = 1)
    {
        $avgRankFieldResultSet = new AverageRankFieldResultSet();
        $totalProfitAVR = 0;
        $temp_array = array();

        $sql_horseid = "SELECT horse_id  FROM `tbl_horses`";
        $sql_raceid = "SELECT race_id  FROM tbl_races";


        // get horse id from database
        $result_horseid = $this->dbConnector->getDbConnection()->query($sql_horseid);
        $horse_id = array();
        if ($result_horseid->num_rows > 0)
        {
            while ($row_id = $result_horseid->fetch_assoc())
            {
                $horse_id[] = $row_id['horse_id'];
            }
        }

        // get race id from database
        $result_raceid = $this->dbConnector->getDbConnection()->query($sql_raceid);
        $race_id = array();
        if ($result_raceid->num_rows > 0)
        {
            while ($row_id = $result_raceid->fetch_assoc())
            {
                $race_id[] = $row_id['race_id'];
            }
        }


        //get distance from database
        for($i = 0; $i < count($race_id); $i++){
//            for($j = 0; $i < count($horse_id); $j++){
            for ($j = 0; $j < 40; $j++) {
                $distance = [];
                $queryD = "SELECT race_distance FROM tbl_hist_results WHERE race_id=" . $race_id[$i] . " and horse_id=" . $horse_id[$j] . " GROUP BY race_distance";
                // get race id from database
                $resultD = $this->dbConnector->getDbConnection()->query($queryD);
                if ($resultD->num_rows > 0) {
                    //get distance for raceid and horseid from database
                    while ($row = $resultD->fetch_assoc()) {
                        $distance[] = $row['race_distance'];
                    }

                    $temp_array = [];
                    try {
                        for ($k = 0; $k < count($distance); $k++) {
                            $query = "SELECT * FROM tbl_hist_results WHERE race_id=" . $race_id[$i] . " and horse_id=" . $horse_id[$j] . " and race_distance='" . $distance[$k] . "' order by horse_position";
                            // get race id from database
                            $result = $this->dbConnector->getDbConnection()->query($query);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_object()) {
                                    $avgRank = number_format($row->rank, 2);
                                    $position = $row->horse_position;
                                    $odds = str_replace("$", "", $row->horse_fixed_odds);
                                    $profit = ($position == "" ? 0 : (($position == 1) ? ((10 * $odds) - 10) : -10));
                                    $totalProfitAVR += $profit;
                                    $avgRankFieldResultSet->calculateAbsoluteTotal($profit);

                                    $temp_array[] = [
                                        'raceId' => $race_id[$i],
                                        'horseId' => $horse_id[$j],
                                        'horse' => '',
                                        'race' => '',
                                        'meeting' => '',
                                        'avgRank' => $avgRank,
                                        'revenue' => $profit,
                                        'total' => $totalProfitAVR
                                    ];
                                    if (count($temp_array) >= $selector)
                                        break;
                                }
                            }
                        }
                    }catch (\Throwable $e) {
                        // todo it fails
                    }
                }
            }
        }

        $avgRankFieldResultSet->setResults($temp_array);

        return $avgRankFieldResultSet;

    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param bool $oddsEnabled
     * @return \App\Model\AverageRankFieldResultSet
     */
    public function generateAvgRankFieldResultSet(Request $request, bool $oddsEnabled, int $limit = null, int $offset = null): AverageRankFieldResultSet
    {
        $avgRankFieldResultSet = new AverageRankFieldResultSet();

        $horseRatingData = $this->generateHorseRatingData($oddsEnabled);
        $horseData = $this->generateHorseData($horseRatingData, $oddsEnabled);

        $mode = $request->query->get('mode');
        if ($mode == null || $mode == "null") {
            $mode = 2;
        }
        $totalProfitAVR = 0;
        if ($limit != null) {
            $sql_raceid = "SELECT race_id  FROM tbl_races LIMIT " . $limit . ' OFFSET ' . $offset;
        } else {
            $sql_raceid = "SELECT race_id  FROM tbl_races";
        }
        $result_raceid = $this->dbConnector->getDbConnection()->query($sql_raceid);

        $realResultsAVGArray = [];
        if ($result_raceid->num_rows > 0)
        {
            // output data of each row
            while ($row_id = $result_raceid->fetch_assoc())
            {
                $resultsForRaceArray = $this->getResultsForRace($row_id['race_id']);
                $temp_array = array();
                //$topIds = $this->generateTopIds($row_id['race_id']);

                /** @var HorseDataModel $horseDatum */
                foreach ($horseData as $horseDatum) {
                    if ($row_id['race_id'] == $horseDatum->getRaceId()) {
                        if (!empty($resultsForRaceArray)) {
                            $position = $this->getPositionByHorseId($resultsForRaceArray, $horseDatum->getHorseId());
                            if ($position) {
                                $horseDatum->setPosition($position);
                            }
                        }

                        $temp_array[] = $horseDatum;
                    }
                }

                usort($temp_array, function (HorseDataModel $a, HorseDataModel $b) {
                    return strcmp($a->getRank(), $b->getRank()) * -1;
                });

                if (count($temp_array) > 0) {
                    try {
                        switch ($mode) {
                            case 1:
                                $real_result = array(
                                    $temp_array[0]
                                );
                                break;
                            case 3:
                                $real_result = array(
                                    $temp_array[0],
                                    $temp_array[1],
                                    $temp_array[2]
                                );
                                break;
                            case 2:
                            default:
                                $real_result = array(
                                    $temp_array[0],
                                    $temp_array[1]
                                );
                                break;
                        }

                        $race = $this->dbConnector->getRaceDetails($row_id['race_id']);
                        $meeting = $this->dbConnector->getMeetingDetails($race->getMeetingId());
                        if (count($real_result) > 0) {
                            /** @var HorseDataModel $horseDataModel */
                            foreach ($real_result as $horseDataModel) {
                                $avgRank = number_format($horseDataModel->getRank(true), 2);
                                //$profit = in_array($horseDataModel->getHorseId(), $topIds) ? ProfitLossCalculationHelper::simpleProfitCalculation($horseDataModel, true) : ProfitLossCalculationHelper::simpleProfitCalculation($horseDataModel);
                                $profit = ProfitLossCalculationHelper::simpleProfitCalculation($horseDataModel);
                                $totalProfitAVR += $profit;
                                $avgRankFieldResultSet->calculateAbsoluteTotal($profit);

                                $realResultsAVGArray[] = [
                                    'raceId' => $horseDataModel->getRaceId(),
                                    'horseId' => $horseDataModel->getHorseId(),
                                    'horse' => $horseDataModel->getHorseName(),
                                    'race' => $race,
                                    'meeting' => $meeting,
                                    'rating' => number_format($horseDataModel->getRating(true), 2),
                                    'avgRank' => $avgRank,
                                    'revenue' => $profit,
                                    'total' => $totalProfitAVR
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        // todo it fails
                    }
                }
            }
        }
        $avgRankFieldResultSet->setResults($realResultsAVGArray);

        return $avgRankFieldResultSet;
    }

    private function generateHorseRatingData(bool $oddsEnabled): array
    {
        $horseRatingData = [];

        $testSql = "SELECT hr.horse_position, hr.horse_id, race_id, AVG(rating) as rating, (SUM(hr.rank)/COUNT(hr.race_id)) AS ranks, horse_fixed_odds FROM tbl_hist_results hr INNER JOIN tbl_horses h ON hr.horse_id = h.horse_id WHERE 1 GROUP BY horse_name, horse_fixed_odds";
        $result = $this->dbConnector->getDbConnection()->query($testSql);
        if ($result->num_rows > 0) {
            while ($horseData = $result->fetch_object()) {
                if (OddsHelper::oddsFilter($horseData->horse_fixed_odds, $oddsEnabled)) {
                    $horseRatingData[$horseData->horse_id][$horseData->race_id] = [
                        'rating' => $horseData->rating,
                        'rank' => $horseData->ranks,
                        'horse_fixed_odds' => $horseData->horse_fixed_odds,
                        'position' => $horseData->horse_position
                    ];
                }
            }
        }

        return $horseRatingData;
    }

    private function generateSelectorHorseData(array $horseRatingData, bool $oddsEnabled): array
    {
        $horseData = [];
        $sql = "SELECT horse_name,hr.horse_id,hr.race_id,hr.horse_position,hr.hist_id, AVG(rating) AS rating, (SUM(hr.rank)/COUNT(hr.race_id)) AS ranks, horse_fixed_odds 
FROM tbl_hist_results hr INNER JOIN tbl_horses h ON hr.horse_id = h.horse_id";
        $result = $this->dbConnector->getDbConnection()->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if (OddsHelper::oddsFilter($row['horse_fixed_odds'], $oddsEnabled)) {
                    $horseData[] = new HorseDataModel(
                        $row['race_id'],
                        $row['horse_id'],
                        $row['horse_name'],
                        $horseRatingData[$row['horse_id']][$row['race_id']]['rating'],
                        $horseRatingData[$row['horse_id']][$row['race_id']]['rank'],
                        0,
                        $horseRatingData[$row['horse_id']][$row['race_id']]['horse_fixed_odds'],
                        $row['hist_id']
                    );
                }
            }
        }

        return $horseData;
    }

    private function generateHorseData(array $horseRatingData, bool $oddsEnabled): array
    {
        $horseData = [];
        $sql = "SELECT horse_name,hr.horse_id,hr.race_id,hr.horse_position,hr.hist_id, AVG(rating) AS rating, (SUM(hr.rank)/COUNT(hr.race_id)) AS ranks, horse_fixed_odds 
FROM tbl_hist_results hr INNER JOIN tbl_horses h ON hr.horse_id = h.horse_id WHERE 1 GROUP BY hr.horse_id, horse_fixed_odds ";
        $result = $this->dbConnector->getDbConnection()->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if (OddsHelper::oddsFilter($row['horse_fixed_odds'], $oddsEnabled)) {
                    $horseData[] = new HorseDataModel(
                        $row['race_id'],
                        $row['horse_id'],
                        $row['horse_name'],
                        $horseRatingData[$row['horse_id']][$row['race_id']]['rating'],
                        $horseRatingData[$row['horse_id']][$row['race_id']]['rank'],
                        0,
                        $horseRatingData[$row['horse_id']][$row['race_id']]['horse_fixed_odds'],
                        $row['hist_id']
                    );
                }
            }
        }

        return $horseData;
    }

    private function generateTopIds(int $raceId): array
    {
        // top ids?!
        $temp = [];
        $sqlQuery = $this->dbConnector->getDbConnection()->query("SELECT * FROM `tbl_temp_hraces` WHERE `race_id`='$raceId'");
        while($tempRacesHorse = $sqlQuery->fetch_object()) {
            $sqlfavg2 = "SELECT *, AVG(rating) rat,AVG(rank) as avgrank FROM `tbl_hist_results` WHERE `race_id`='".$raceId."' AND `horse_id`='$tempRacesHorse->horse_id' GROUP BY horse_id";
            $sqlavg2 = $this->dbConnector->getDbConnection()->query($sqlfavg2);

            while($resavg2 = $sqlavg2->fetch_assoc()) {
                $temp[] = $resavg2;
            }

        }
        usort($temp, function($a, $b)
        {
            return ($a["avgrank"] <= $b["avgrank"]) ? -1 : 1;
        });
        $temp = array_reverse($temp);
        $table = array_slice($temp, 0, 0);
        $top_ids = [];
        foreach ($table as $arr){
            $top_ids[] = $arr['horse_id'];
        }

        return $top_ids;
    }

    /**
     * @param int $raceId
     * @return array
     */
    private function getResultsForRace(int $raceId): array
    {
        $mysqli = $this->dbConnector->getDbConnection();
        $resultsForRaceArray = [];
        $resultsForRace = $mysqli->query("SELECT * FROM `tbl_results` WHERE `race_id`='" . $raceId . "' ORDER BY position ASC");
        if ($resultsForRace->num_rows > 0) {
            // output data of each row
            while ($raceResults = $resultsForRace->fetch_object()) {
                $resultsForRaceArray[] = [
                    'raceResultPosition' => $raceResults->position,
                    'raceId' => $raceId,
                    'horse_id' => $raceResults->horse_id
                ];
            }
        }

        return $resultsForRaceArray;
    }

    /**
     * Find horse position in the target race
     *
     * @param array $resultsForRaceArray
     * @param int $horseId
     * @return int
     */
    private function getPositionByHorseId(array $resultsForRaceArray, int $horseId): int {
        foreach ($resultsForRaceArray as $results) {
            if ($results['horse_id'] == $horseId) {
                return $results['raceResultPosition'];
            }
        }

        return 0;
    }
}
