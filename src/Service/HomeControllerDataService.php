<?php

namespace App\Service;

use App\DataTransformer\HorseDataModelTransformer;
use App\Helper\OddsHelper;
use App\Helper\ResultsDataHelper;
use App\Model\App\HorseDataModel;
use App\Model\AverageRankFieldResultSet;
use App\Model\RatingFieldResultSet;
use Symfony\Component\HttpFoundation\Request;

class HomeControllerDataService
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
     * @TODO REFACTOR
     * @TODO Move to another service
     *
     * @return AverageRankFieldResultSet
     */
    public function generateAvgRankFieldResultSet(Request $request, $oddsFilter): AverageRankFieldResultSet
    {
        // TODO refactor starts here
        $avgRankFieldResultSet = new AverageRankFieldResultSet();

        $horseRatingData = array();
        $horse_data = array();

        $sql = "SELECT horse_name,hr.horse_id,hr.race_id,hr.horse_position, AVG(rating) AS rating, (SUM(hr.rank)/COUNT(hr.race_id)) AS ranks, horse_fixed_odds 
FROM tbl_hist_results hr INNER JOIN tbl_horses h ON hr.horse_id = h.horse_id WHERE 1 GROUP BY hr.horse_id ";
        $result = $this->dbConnector->getDbConnection()->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $horseRatingData[$row['horse_name']] = array(
                    'rating' => $row['rating'],
                    'rank' => $row['ranks'],
                    'horse_fixed_odds' => $row['horse_fixed_odds']
                );
                if (OddsHelper::oddsFilter($row['horse_fixed_odds'], $oddsFilter)) {
                    $horse_data[] = array(
                        $row['race_id'],
                        $row['horse_name'],
                        $row['rating'],
                        $row['ranks'],
                        $row['horse_position'],
                        $row['horse_fixed_odds'],
                        // modify by JFrost
                        $row['horse_id'],
                    );
                }
            }
        }

        $totalProfitAVR = 0;
        $totalLossAVR = 0;

        $sql_raceid = "SELECT race_id  FROM tbl_races";
        $result_raceid = $this->dbConnector->getDbConnection()->query($sql_raceid);

        $realResultsAVGArray = [];
        if ($result_raceid->num_rows > 0) {

            // output data of each row
            while ($row_id = $result_raceid->fetch_assoc()) {

                $temp_array = array();
                $race_data = array_column($horse_data, 0);

                foreach ($race_data as $k => $r) {
                    if ($row_id['race_id'] == $r) {
                        $temp_array[] = $horse_data[$k];
                    }
                }

                usort($temp_array, function ($a, $b) {
                    // if ($a[2] == $b[2])
                    //     return 0;
                    // return ($a[2] < $b[2]) ? -1 : 1;
                    return strcmp($a[2], $b[2]) * -1;
                });

                if (count($temp_array) > 0) {
                    try {
                        switch ($request->query->get('mode')) {
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

                        $ratin = array();

                        $race = $this->dbConnector->getRaceDetails($row_id['race_id']);
                        $meeting = $this->dbConnector->getMeetingDetails($race->getMeetingId());
                        if (count($real_result) > 0) {
                            foreach ($real_result as $row) {
                                $ratin[] = number_format(floatval($row[2]), 0);
                                $avgrank = number_format(floatval($row[3]), 2);
                                $odds = str_replace("$", "", $row[5]);
                                $position = intval($row[4]);
                                $profit = $row[4] == "" ? 0 : (($position == 1) ? ((10 * floatval($odds)) - 10) : -10);
                                $totalLossAVR += ($profit < 0) ? 10 : 0;
                                $totalProfitAVR += $profit;
                                $avgRankFieldResultSet->calculateAbsoluteTotal($profit);

                                $realResultsAVGArray[] = [
                                    'raceId' => $row_id['race_id'],
                                    'horseId' => $row[6],
                                    'horse' => $row[1],
                                    'race' => $race,
                                    'meeting' => $meeting,
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

    /**
     * @param Request $request
     * @param $oddsFilter
     * @return RatingFieldResultSet
     */
    public function generateRatingFieldResultSet(Request $request, $oddsFilter): RatingFieldResultSet
    {
        $horseRatingData = $this->generateHorseRatingData();
        $horseResultData = $this->generateHorseResultData();
        $horseDataModelSet = $this->generateHorseDataModelSet($oddsFilter, $horseRatingData, $horseResultData);

        return $this->generateRatingFieldResults($horseDataModelSet, $request->query->get('mode'));
    }

    // TODO HorseRatingDataModel
    protected function generateHorseRatingData(): array
    {
        $horseRatingData = [];
        $sql = "SELECT horse_name, AVG(rating) as rating, (SUM(hr.rank)/COUNT(hr.race_id)) AS ranks, horse_fixed_odds FROM tbl_hist_results hr INNER JOIN tbl_horses h ON hr.horse_id = h.horse_id WHERE 1 GROUP BY horse_name";
        $result = $this->dbConnector->getDbConnection()->query($sql);
        if ($result->num_rows > 0) {
            while ($horseData = $result->fetch_object()) {
                $horseRatingData[$horseData->horse_name] = array(
                    'rating' => $horseData->rating,
                    'rank' => $horseData->ranks,
                    'horse_fixed_odds' => $horseData->horse_fixed_odds
                );
            }
        }

        return $horseRatingData;
    }

    // TODO HorseResultDataModel
    protected function generateHorseResultData(): array
    {
        $horseResultData = [];
        $sql = "select * from `tbl_results`";
        $result = $this->dbConnector->getDbConnection()->query($sql);
        if ($result->num_rows > 0) {
            while ($tbResult = $result->fetch_object()) {
                if (array_key_exists($tbResult->horse_id, $horseResultData)) {
                    if (ResultsDataHelper::isResultBetter($tbResult, $horseResultData[$tbResult->horse_id])) {
                        $horseResultData[$tbResult->horse_id] = [
                            'race_id' => $tbResult->race_id,
                            'position' => $tbResult->position
                        ];
                    }
                } else {
                    $horseResultData[$tbResult->horse_id] = [
                        'race_id' => $tbResult->race_id,
                        'position' => $tbResult->position
                    ];
                }
            }
        }

        return $horseResultData;
    }

    // TODO refactor
    protected function generateHorseDataModelSet($oddsFilter, $horseRatingData, $horseResultData): array
    {
        $horse_data = [];
        $sql = "SELECT * FROM `tbl_horses` ORDER BY horse_id ASC";
        $result = $this->dbConnector->getDbConnection()->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {

                $rating = '';
                $rank = '';
                $odds = '';

                if (isset($horseRatingData[$row['horse_name']])) {
                    $rating = $horseRatingData[$row['horse_name']]["rating"];
                    $rank = $horseRatingData[$row['horse_name']]["rank"];
                    $odds = $horseRatingData[$row['horse_name']]['horse_fixed_odds'];
                }

                $position = '';
                $race_id = '';
                if (isset($horseResultData[$row['horse_id']])) {
                    $race_id = $horseResultData[$row['horse_id']]['race_id'];
                    $position = $horseResultData[$row['horse_id']]['position'];
                }

                if (OddsHelper::oddsFilter($odds, $oddsFilter)) {
                    $horse_data[] = new HorseDataModel(
                        $race_id,
                        $row['horse_id'],
                        $row['horse_name'],
                        $rating,
                        $rank,
                        $position,
                        $odds
                    );
                }
            }
        }

        return $horse_data;
    }

    /**
     * @param $horseDataModelSet
     * @param int|null $mode
     * @return RatingFieldResultSet
     */
    protected function generateRatingFieldResults($horseDataModelSet, ?int $mode): RatingFieldResultSet
    {
        $ratingFieldResultSet = new RatingFieldResultSet();
        $currentFieldAbsoluteTotal = 0;

        $sql_raceid = "SELECT race_id FROM tbl_races";
        $result_raceid = $this->dbConnector->getDbConnection()->query($sql_raceid);

        $ratingFieldResultsArray = [];
        if ($result_raceid->num_rows > 0) {

            // output data of each row
            while ($row_id = $result_raceid->fetch_assoc()) {
                $temp_array = array();
                $race_data = (new HorseDataModelTransformer($horseDataModelSet))->transform();
                //$race_data = array_column($horse_data, 0);

                foreach ($race_data as $k => $r) {
                    if ($row_id['race_id'] == $r) {
                        $temp_array[] = $horseDataModelSet[$k];
                    }
                }
                /** @var $a HorseDataModel */
                usort($temp_array, function ($a, $b) {
                    if ($a->getRating() === $b->getRating())
                        return 0;
                    return ($a->getRating() > $b->getRating()) ? -1 : 1;
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
                            /** @var HorseDataModel $horseModelData */
                            foreach ($real_result as $horseModelData) {
                                $profit = $horseModelData->getPosition(false) == "" ? 0 : (($horseModelData->getPosition() == 1) ? ((10 * $horseModelData->getOdds(true)) - 10) : -10);
                                $currentFieldAbsoluteTotal += $profit;
                                $ratingFieldResultSet->calculateAbsoluteTotal($profit);

                                $ratingFieldResultsArray[] = [
                                    'horseId' => $horseModelData->getHorseId(),
                                    'raceId' => $race->getRaceId(),
                                    'race' => $race,
                                    'meeting' => $meeting,
                                    'horse' => $horseModelData->getHorseName(),
                                    'revenue' => $profit,
                                    'total' => $currentFieldAbsoluteTotal
                                ];

                            }
                        }
                    } catch (\Throwable $e) {
                        // todo it fails
                    }
                }
            }
        }
        $ratingFieldResultSet->setResults($ratingFieldResultsArray);

        return $ratingFieldResultSet;
    }
}