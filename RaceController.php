<?php

namespace App\Controller;

use App\Helper\OddsHelper;
use App\Helper\ProfitLossCalculationHelper;
use App\Model\App\Horse;
use App\Model\App\HorseDataModel;
use App\Service\DBConnector;
use App\Service\Homepage\RatingFieldResultSetService;
use App\Service\PrettyLogger;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class RaceController extends AbstractController
{
    protected $logger;
    protected $dbConnector;
    protected $session;
    protected $ratingFieldResultSetService;
    protected $selector;


    /**
     * @throws Exception
     */
    public function __construct(SessionInterface $session, RatingFieldResultSetService $ratingFieldResultSetService)
    {
        $this->logger = new PrettyLogger(__FILE__, "race_details.txt");
        $this->logger->setLevel('DEBUG');
        $this->dbConnector = new DBConnector();
        $this->session = $session;
        $this->ratingFieldResultSetService = $ratingFieldResultSetService;
        $this->selector = $_ENV['selector'];
    }

    /**
     * @Route("/races/meeting/{meeting}", name="races_index")
     *
     * @param $meeting
     * @return Response
     */
    public function showAll($meeting): Response
    {
        $mysqli = $this->dbConnector->getDbConnection();
        $meetingDetails = $this->dbConnector->getMeetingDetails($meeting);
        $this->session->set('meeting_name', $meetingDetails->getMeetingName());
        $this->session->set('meeting_id', $meetingDetails->getMeetingId());
        $races = $mysqli->query("SELECT * FROM `tbl_races` WHERE `meeting_id`=" . $meetingDetails->getMeetingId() . " ORDER by race_order ASC");

        return $this->render('races.html.twig', [
            'races' => $races,
            'meetingDetails' => $meetingDetails,
            'meetingId' => $meeting
        ]);
    }

    /**
     * @Route("/races/meeting/{meeting}/race/{race}/{average?default}", name="races_details")
     *
     * @param $meeting
     * @param $race
     * @param $average
     * @return Response
     */
    public function showOne($meeting, $race, $average): Response
    {
        $mysqli = $this->dbConnector->getDbConnection();
        $raceDetails = $this->dbConnector->getRaceDetails($race);
        $meetingDetails = $this->dbConnector->getMeetingDetails($meeting);
        $races = $mysqli->query("SELECT * FROM `tbl_races` WHERE `meeting_id`=" . $meetingDetails->getMeetingId() . " ORDER by race_order ASC");

        $resultsForRaceArray = $this->getResultsForRace($race);
        $resultsCombinedArray = [];

        $this->session->set('meeting_id', $meeting);
        $this->session->set('meeting_name', $meetingDetails->getMeetingName());

        $sqlfavg = "SELECT *, AVG(`rating`) rat, AVG(`rank`) avgrank FROM `tbl_hist_results` WHERE `race_id`='" . $race . "' GROUP BY `horse_id`";
        $max_1 = $max_2 = 0;
        $geting = $mysqli->query($sqlfavg);
        $ratin = array();
        while ($gnow = $geting->fetch_object()) {
            $ratin[] = number_format($gnow->rat, 2);
        }
        if (count($ratin) > '0') {
            $ismaxrat = max($ratin);

            $max_1 = $max_2 = -1;
            $maxused = 0;

            for ($i = 0; $i < count($ratin); $i++) {
                if ($ratin[$i] > $max_1) {
                    $max_2 = $max_1;
                    $max_1 = $ratin[$i];
                } else if ($ratin[$i] > $max_2) {
                    $max_2 = $ratin[$i];
                }
            }
        }

        $horseRatingData = $this->generateHorseRatingData(false);
        $horseData = $this->generateHorseData($horseRatingData, false, $resultsForRaceArray);
        $mainPageData = $this->strippedMainPageRecords($race, $horseData);

        // normal calculations
        $getrnum = $mysqli->query("SELECT * FROM `tbl_temp_hraces` WHERE `race_id`='$race'");
        //$topIds = $this->generateTopIds($race);
        while ($ghorse = $getrnum->fetch_object()) {
            $horseDetails = $this->dbConnector->getHorseDetails($ghorse->horse_id);
            // This if condition shows from the homepage, without entering average or showing all

            // IF averages is not set
            if ($average !== "average") {
                $resultsCombinedArray = $this->generateTableRowsForHistoricResults($race, $ghorse, $horseDetails, $resultsCombinedArray);

            // IF AVERAGES IS SET!!!! (avg=1)
            } else {
                // default view
                $resultsCombinedArray = $this->generateTableRowsForHistoricResultsAVG($race, $ghorse, $max_1, $max_2, $horseDetails, $resultsCombinedArray, $horseRatingData, $mainPageData);
            }
        }

        // TODO END REFACTOR

        return $this->render('race.html.twig', [
            'average' => $average,
            'raceId' => $race,
            'meetingId' => $meeting,
            'raceDetails' => $raceDetails,
            'meetingDetails' => $meetingDetails,
            'races' => $races,
            'resultsForRace' => $resultsForRaceArray,
            'resultsCombinedArray' => $resultsCombinedArray
        ]);
    }

    /**
     * @param int $raceId
     * @return array
     */
    protected function getResultsForRace(int $raceId): array
    {
        $mysqli = $this->dbConnector->getDbConnection();
        $resultsForRaceArray = [];
        $resultsForRace = $mysqli->query("SELECT * FROM `tbl_results` WHERE `race_id`='" . $raceId . "' ORDER BY position ASC");
        if ($resultsForRace->num_rows > 0) {
            // output data of each row
            while ($raceResults = $resultsForRace->fetch_object()) {
                $horseDetails = $this->dbConnector->getHorseDetails($raceResults->horse_id);
                $raceDetails = $this->dbConnector->getRaceDetails($raceResults->race_id);
                $meetingDetails = $this->dbConnector->getMeetingDetails($raceDetails->getMeetingId());

                $resultsForRaceArray[] = [
                    'raceResultPosition' => $raceResults->position,
                    'horseName' => $horseDetails->getHorseName(),
                    'roundDistance' => $raceDetails->getRoundDistance(),
                    'meetingName' => $meetingDetails->getMeetingName(),
                    'raceId' => $raceId,
                    'raceName' => $raceDetails->getRaceTitle(),
                    'horse_id' => $raceResults->horse_id
                ];
            }
        }

        return $resultsForRaceArray;
    }

    /**
     * @param int $raceId
     * @param $ghorse
     * @param \App\Model\App\Horse $horseDetails
     * @param array $resultsCombinedArray
     * @return array
     */
    protected function generateTableRowsForHistoricResults(int $raceId, $ghorse, Horse $horseDetails, array $resultsCombinedArray): array
    {
        $distanceDetails = $this->dbConnector->getDistanceArray($raceId);
        $mysqli = $this->dbConnector->getDbConnection();
        foreach ($distanceDetails as $distance) {
            $sqlnow = $mysqli->query("SELECT *  FROM `tbl_hist_results` WHERE `race_id`='" . $raceId . "' AND `horse_id`='$ghorse->horse_id' AND `race_distance`='$distance'");
            if ($sqlnow->num_rows > 0) {
                while ($resnow = $sqlnow->fetch_object()) {
                    $resultsCombinedArray[] = [
                        'horseNum' => $ghorse->horse_num,
                        'horseName' => $horseDetails->getHorseName(),
                        'horseLatestResults' => $horseDetails->getHorseLatestResults(),
                        'horseFxOdds' => $resnow->horse_fixed_odds,
                        'raceDistance' => $resnow->race_distance,
                        'raceSectional' => $resnow->sectional,
                        'raceTime' => $resnow->race_time,
                        'raceHorsePosition' => $resnow->horse_position,
                        'raceHorseLength' => $resnow->length,
                        'raceWeight' => $resnow->horse_weight,
                        'horseWeight' => $ghorse->horse_weight,
                        'handicap' => number_format($resnow->handicap, 3),
                        'rating' => $resnow->rating,
                        'rank' => $resnow->rank,
                        'raceId' => $raceId,
                        'horseId' => $horseDetails->getHorseId(),
                        'histId' => $resnow->hist_id
                    ];
                }
            } else {
                // Horse seems to not have historic results
                $resultsCombinedArray[] = [
                    'horseNum' => $ghorse->horse_num,
                    'horseName' => $horseDetails->getHorseName(),
                    'horseLatestResults' => $horseDetails->getHorseLatestResults(),
                    'horseFxOdds' => $ghorse->horse_fxodds,
                    'raceDistance' => null,
                    'raceSectional' => null,
                    'raceTime' => null,
                    'raceHorsePosition' => null,
                    'raceWeight' => null,
                    'raceWeight' => null,
                    'horseWeight' => null,
                    'handicap' => null,
                    'rating' => null,
                    'rank' => null,
                    'raceId' => $raceId,
                    'horseId' => $horseDetails->getHorseId(),
                    'histId' => null
                ];
            }
            $this->array_sort_by_column($resultsCombinedArray, 'raceDistance');
            $tmp = array();
            $tmp = array_slice($resultsCombinedArray, 0, $this->selector);
            $resultsCombinedArray = $tmp;
        }

        return $resultsCombinedArray;
    }

    /**
     * @param $race
     * @param $ghorse
     * @param $max_1
     * @param $max_2
     * @param $horseDetails
     * @param array $top_ids
     * @param array $resultsCombinedArray
     * @return array
     */
    protected function generateTableRowsForHistoricResultsAVG($race, $ghorse, $max_1, $max_2, Horse $horseDetails, array $resultsCombinedArray, array $horseRatingData, array $mainPageData): array
    {
        $mysqli = $this->dbConnector->getDbConnection();
        $sqlfavg = "SELECT *, AVG(`rating`) as rat, AVG(`rank`) as avgrank FROM `tbl_hist_results` WHERE `race_id`='" . $race . "' AND `horse_id`='$ghorse->horse_id' GROUP BY `horse_id`";

        $cnt = 1;
        $queryResult = $mysqli->query($sqlfavg);
        if ($queryResult->num_rows > 0) {
            while ($resavg = $queryResult->fetch_object()) {
                // This is average rating for horse in race
                $ratingData = $horseRatingData[$horseDetails->getHorseId()][$race];
                $averageRatingForHorseInRace = number_format($ratingData['rating'], 2);
                $averageRankForHorseInRace = number_format($ratingData['rank'], 2);
                $odds = str_replace("$", "", $resavg->horse_fixed_odds);
                $position = isset($mainPageData[$horseDetails->getHorseName()]) ? $mainPageData[$horseDetails->getHorseName()]['position'] : '';
                $resultsCombinedArray[] = [
                    'horseId' => $horseDetails->getHorseId(),
                    'horseNum' => $ghorse->horse_num,
                    'horseName' => $horseDetails->getHorseName(),
                    'horseLatestResults' => $horseDetails->getHorseLatestResults(),
                    'horseFxOdds' => $resavg->horse_fixed_odds,
                    'raceDistance' => null,
                    'raceSectional' => null,
                    'raceTime' => null,
                    'raceHorsePosition' => null,
		    'raceLength' => $resavg->length,
                    'raceWeight' => $resavg->horse_weight,
                    'horseWeight' => $resavg->horse_weight,
                    'rating' => $averageRatingForHorseInRace,
                    'profitLoss' => ProfitLossCalculationHelper::profitOrLossCalculation($max_1, $max_2, number_format($resavg->rat, 2), $odds, $position, $horseDetails->getHorseName()),
                    'rank' => $averageRankForHorseInRace,
                    'profit' => isset($mainPageData[$horseDetails->getHorseName()]) ? $mainPageData[$horseDetails->getHorseName()]['revenue'] : null //in_array($horseDetails->getHorseId(), $top_ids) ? ProfitLossCalculationHelper::simpleProfitCalculation($horseDataModel, true) : ProfitLossCalculationHelper::simpleProfitCalculation($horseDataModel)
                ];
                ++$cnt;
            }
        } else {
            $resultsCombinedArray[] = [
                'horseId' => $horseDetails->getHorseId(),
                'horseNum' => $ghorse->horse_num,
                'horseName' => $horseDetails->getHorseName(),
                'horseLatestResults' => $horseDetails->getHorseLatestResults(),
                'horseFxOdds' => $ghorse->horse_fxodds,
                'raceDistance' => null,
                'raceSectional' => null,
                'raceTime' => null,
                'raceHorsePosition' => null,
                'raceLength' => null,
                'raceWeight' => null,
                'horseWeight' => null,
                'rating' => null,
                'profitLoss' => null,
                'rank' => null,
                'profit' => null
            ];
        }

        return $resultsCombinedArray;
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

    private function strippedMainPageRecords(int $raceId, array $horseData)
    {
        $sql_raceid = "SELECT race_id  FROM tbl_races WHERE `race_id`=" . $raceId;
        $result_raceid = $this->dbConnector->getDbConnection()->query($sql_raceid);

        $realResultsAVGArray = [];
        if ($result_raceid->num_rows > 0)
        {
            // output data of each row
            while ($row_id = $result_raceid->fetch_assoc())
            {
                $temp_array = array();
                /** @var HorseDataModel $horseDatum */
                foreach ($horseData as $horseDatum) {
                    if ($row_id['race_id'] == $horseDatum->getRaceId()) {
                        $temp_array[] = $horseDatum;
                    }
                }

                usort($temp_array, function (HorseDataModel $a, HorseDataModel $b) {
                    return strcmp($a->getRank(), $b->getRank()) * -1;
                });

                if (count($temp_array) > 0) {
                    try {
                        $real_result = array(
                            $temp_array[0],
                            $temp_array[1],
                            $temp_array[2]
                        );

                        if (count($real_result) > 0) {
                            /** @var HorseDataModel $horseDataModel */
                            foreach ($real_result as $horseDataModel) {
                                $profit = ProfitLossCalculationHelper::simpleProfitCalculation($horseDataModel);
                                $realResultsAVGArray[$horseDataModel->getHorseName()] = [
                                    'revenue' => $profit,
                                    'position' => $horseDataModel->getPosition(true),
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        // todo it fails
                    }
                }
            }
        }

        return $realResultsAVGArray;
    }

    private function generateHorseData(array $horseRatingData, bool $oddsEnabled, array $resultsForRaceArray = []): array
    {
        $horseData = [];
        $sql = "SELECT horse_name,hr.horse_id,hr.race_id,hr.horse_position,hr.hist_id, AVG(rating) AS rating, (SUM(hr.rank)/COUNT(hr.race_id)) AS ranks, horse_fixed_odds 
FROM tbl_hist_results hr INNER JOIN tbl_horses h ON hr.horse_id = h.horse_id WHERE 1 GROUP BY hr.horse_id, horse_fixed_odds ";
        $result = $this->dbConnector->getDbConnection()->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if (OddsHelper::oddsFilter($row['horse_fixed_odds'], $oddsEnabled)) {
                    // Go thru the horse rating data list and update it with actual racing position
                    if (!empty($resultsForRaceArray)) {
                        $position = $this->getPositionByHorseId($resultsForRaceArray, $row['horse_id']);
                    }

                    $horseData[] = new HorseDataModel(
                        $row['race_id'],
                        $row['horse_id'],
                        $row['horse_name'],
                        $horseRatingData[$row['horse_id']][$row['race_id']]['rating'],
                        $horseRatingData[$row['horse_id']][$row['race_id']]['rank'],
                        $position,
                        $horseRatingData[$row['horse_id']][$row['race_id']]['horse_fixed_odds'],
                        $row['hist_id']
                    );
                }
            }
        }

        return $horseData;
    }

    private function generateHorseRatingData(bool $oddsEnabled): array
    {
        $horseRatingData = [];

        $testSql = "SELECT hr.horse_position, hr.horse_id, hr.race_id, AVG(rating) as rating, (SUM(hr.rank)/COUNT(hr.race_id)) AS ranks, horse_fixed_odds FROM tbl_hist_results hr INNER JOIN tbl_horses h ON hr.horse_id = h.horse_id WHERE 1 GROUP BY horse_name, horse_fixed_odds";
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

    /* sort by col  */
    private function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
        $sort_col = array();
        foreach ($arr as $key => $row) {
            $sort_col[$key] = $row[$col];
        }

        array_multisort($sort_col, $dir, $arr);
    }
}
