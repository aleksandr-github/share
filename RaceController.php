<?php

namespace App\Controller;

use App\Model\App\Horse;
use App\Service\DBConnector;
use App\Service\PrettyLogger;
use Exception;
use mysqli;
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

    /**
     * @throws Exception
     */
    public function __construct(SessionInterface $session)
    {
        $this->logger = new PrettyLogger(__FILE__, "race_details.txt");
        $this->logger->setLevel('DEBUG');
        $this->dbConnector = new DBConnector();
        $this->session = $session;
    }

    /**
     * @Route("/races/meeting/{meeting}", name="races_index")
     *
     * @param Request $request
     * @param $meeting
     * @return Response
     */
    public function showAll(Request $request, $meeting): Response
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

        // TODO DO REFACTOR IMMEDIATELY
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

        $this->logger->log("Maxing distributes: " . $max_1 . " == " . $max_2 . "<br>");

        $getrnum = $mysqli->query("SELECT * FROM `tbl_temp_hraces` WHERE `race_id`='$race'");
        $temp = [];
        while ($ghorse = $getrnum->fetch_object()) {
            $sqlfavg = "SELECT *, AVG(`rating`) as rat, AVG(`rank`) as avgrank FROM `tbl_hist_results` WHERE `race_id`='" . $race . "' AND `horse_id`='$ghorse->horse_id' GROUP BY `horse_id`";
            $sqlavg = $mysqli->query($sqlfavg);

            while ($resavg = $sqlavg->fetch_assoc()) {
                $temp[] = $resavg;
            }

        }

        $getrnum = $mysqli->query("SELECT * FROM `tbl_temp_hraces` WHERE `race_id`='$race'");

        usort($temp, function($a, $b) {
            return ($a["avgrank"] <= $b["avgrank"]) ? -1 : 1;
        });

        $temp = array_reverse($temp);
        $table = array_slice($temp, 0, 2);
        $top_ids = [];
        foreach ($table as $arr) {
            $top_ids[] = $arr['horse_id'];
        }
        $numRows = $getrnum->num_rows;
        while ($ghorse = $getrnum->fetch_object()) {
            $horseDetails = $this->dbConnector->getHorseDetails($ghorse->horse_id);
            // This if condition shows from the homepage, without entering average or showing all

            // IF averages is not set
            if ($average !== "average") {
                $resultsCombinedArray = $this->generateTableRowsForHistoricResults($race, $ghorse, $horseDetails, $resultsCombinedArray);
            // IF AVERAGES IS SET!!!! (avg=1)
            } else {
                // default view
                $resultsCombinedArray = $this->generateTableRowsForHistoricResultsAVG($race, $ghorse, $max_1, $max_2, $horseDetails, $top_ids, $resultsCombinedArray);
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
            'resultsForRace' => $resultsForRaceArray, // @TODO REFACTOR,
            'resultsCombinedArray' => $resultsCombinedArray // @TODO REFACTOR
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
                    'raceName' => $raceDetails->getRaceTitle()
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
        $mysqli = $this->dbConnector->getDbConnection();
        $sqlnow = $mysqli->query("SELECT *  FROM `tbl_hist_results` WHERE `race_id`='" . $raceId . "' AND `horse_id`='$ghorse->horse_id'");
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
                'horseWeight' => null,
                'handicap' => null,
                'rating' => null,
                'rank' => null,
                'raceId' => $raceId,
                'horseId' => $horseDetails->getHorseId(),
                'histId' => null
            ];
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
    protected function generateTableRowsForHistoricResultsAVG($race, $ghorse, $max_1, $max_2, Horse $horseDetails, array $top_ids, array $resultsCombinedArray): array
    {
        $mysqli = $this->dbConnector->getDbConnection();
        $sqlfavg = "SELECT *, AVG(`rating`) as rat, AVG(`rank`) as avgrank FROM `tbl_hist_results` WHERE `race_id`='" . $race . "' AND `horse_id`='$ghorse->horse_id' GROUP BY `horse_id`";
        // $sqlfavg = "SELECT *, AVG(rating) rat,AVG(rank) as avgrank FROM `tbl_hist_results` WHERE `race_id`='".$race_id."' GROUP BY horse_id";

        $cnt = 1;
        $queryResult = $mysqli->query($sqlfavg);
        if ($queryResult->num_rows > 0) {
            while ($resavg = $queryResult->fetch_object()) {
                // This is average rating for horse in race
                $averageRatingForHorseInRace = number_format($resavg->rat, 2);
                $averageRankForHorseInRace = number_format($resavg->avgrank, 2);
                $odds = str_replace("$", "", $resavg->horse_fixed_odds);

                $position = "";
                $posres = $mysqli->query("SELECT position FROM `tbl_results` WHERE `race_id`='" . $race . "' AND `horse_id`='$ghorse->horse_id' LIMIT 1");
                while ($prow = $posres->fetch_object()) {
                    $position = $prow->position;
                }

                if ($cnt < 3) {
                    //$position = $resavg->horse_position;
                    if (!empty($position)) {
                        if ($position < 2) {
                            $profit = 10 * floatval($odds) - 10;
                        } else {
                            $profit = -10;
                        }
                    } else {
                        $profit = "";
                    }
                } else {
                    $profit = "";
                }

                $resultsCombinedArray[] = [
                    'horseNum' => $ghorse->horse_num,
                    'horseName' => $horseDetails->getHorseName(),
                    'horseLatestResults' => $horseDetails->getHorseLatestResults(),
                    'horseFxOdds' => $resavg->horse_fixed_odds,
                    'raceDistance' => null,
                    'raceSectional' => null,
                    'raceTime' => null,
                    'raceHorsePosition' => null,
                    'raceWeight' => $resavg->horse_weight,
                    'horseWeight' => $resavg->horse_weight,
                    'rating' => $averageRatingForHorseInRace,
                    'profitLoss' => $this->profitOrLossCalculation($max_1, $max_2, $averageRatingForHorseInRace, $odds, $position),
                    'rank' => $averageRankForHorseInRace,
                    'profit' => ((!in_array($horseDetails->getHorseId(), $top_ids)) ? null : $profit)
                ];
                ++$cnt;
            }
        } else {
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
                'horseWeight' => null,
                'rating' => null,
                'profitLoss' => null,
                'rank' => null,
                'profit' => null
            ];
        }

        return $resultsCombinedArray;
    }

    private function profitOrLossCalculation($max_1, $max_2, $rating, $odds, $position)
    {
        $profitOrLoss = "";
        //$position = $resavg->horse_position;

        if (!empty($position)) {
            if ($rating && $position > 2) {
                if ($rating > 0) {
                    if ($rating == $max_1 || $rating == $max_2) {
                        $profitOrLoss = 10 * 0 - 10;
                    } else {
                        $profitOrLoss = "";
                    }
                }
            } else {
                if ($rating > 0) {
                    if ($rating == $max_1 || $rating == $max_2) {
                        //  $pos =  explode('/', $resavg->horse_position);
                        //  $position =  intval($pos[0]);

                        if ($position != 1) {
                            $profitOrLoss = 10 * 0 - 10;
                        } else {
                            $profitOrLoss = 10 * $odds - 10;
                        }
                    } else {
                        $profitOrLoss = "";
                    }
                }
            }
        } else {
            $profitOrLoss = "";
        }

        return $profitOrLoss;
    }
}