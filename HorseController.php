<?php

namespace App\Controller;

use App\Service\DBConnector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HorseController extends AbstractController
{
    protected $dbConnector;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->dbConnector = new DBConnector();
    }

    /**
     * @Route("/horse", name="horse_index")
     * @return Response
     */
    public function index(): Response
    {
        $selector = 2;
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

        $combine = array();
        for($i = 0; $i < count($race_id); $i++){
            $combine[] = $horse_id[$i] . 'combine' . $race_id[$i];
        }

        //get distance from database
//        for($i = 0; $i < count($race_id); $i++){
        for($i = 0; $i < 2; $i++){
//            for($j = 0; $i < count($horse_id); $j++){
            for($j = 0; $i < 2; $j++){
                $distance = [];
                $query = "SELECT race_distance FROM tbl_hist_results WHERE race_id=".$race_id[$i]." and horse_id=".$horse_id[$j]." GROUP BY race_distance";
                // get race id from database
                $result = $this->dbConnector->getDbConnection()->query($query);
                if ($result->num_rows > 0)
                {
                    while ($row = $result->fetch_assoc())
                    {
                        $distance[] = $row['race_distance'];
                    }
                }
                for($k = 0; $k < count($distance); $k++){
                    $temp_array = [];
                    $query = "SELECT * FROM tbl_hist_results WHERE race_id=".$race_id[$i]." and horse_id=".$horse_id[$j]." and race_distance='".$distance[$k]."' order by horse_position";
                    // get race id from database
                    $result = $this->dbConnector->getDbConnection()->query($query);
                    if ($result->num_rows > 0)
                    {
                        while ($row = $result->fetch_object())
                        {
                            $temp_array[] = [
                                'rating' => $row->rating,
                                'rank' => $row->ranks,
                                'horse_fixed_odds' => $row->horse_fixed_odds,
                                'position' => $row->horse_position
                            ];
                            if(count($temp_array) >= $selector)
                                break;
                        }
                    }
                }

            }
        }

        return $this->render('horses.html.twig', [
            'horses' => $horse_id,
            'race_ids' => $race_id,
            'combines' => $combine,
            'temp_arrays' => $temp_array
        ]);
    }

    /**
     * @Route("/horse/{horse}", name="horse_details")
     *
     * @param $horse
     * @return Response
     */
    public function showOne($horse): Response
    {
        $mysqli = $this->dbConnector->getDbConnection();
        $sql = "SELECT * FROM `tbl_horses` WHERE `horse_id`='$horse'";
        $result = $mysqli->query($sql);

        $horseDetails = [];
        if ($result->num_rows > 0) {
            // output data of each row
            while ($row = $result->fetch_object()) {
                // horse meetings TODO UGLY
                $sql = "SELECT * FROM tbl_meetings
                LEFT JOIN tbl_races ON tbl_meetings.meeting_id = tbl_races.meeting_id
                LEFT JOIN tbl_results ON tbl_races.race_id = tbl_results.race_id
                WHERE tbl_results.horse_id = " . $horse . " GROUP BY tbl_meetings.meeting_id";
                $result = $mysqli->query($sql);

                $horseMeetings = [];
                if ($result->num_rows > 0) {
                    // output data of each row
                    while ($meetingRow = $result->fetch_object()) {
                        $horseMeetings[] = [
                            'meetingId' => $meetingRow->meeting_id,
                            'meetingName' => $meetingRow->meeting_name,
                            'meetingDate' => $meetingRow->meeting_date
                        ];
                    }
                }

                $horseDetails[] = [
                    'horseId' => $row->horse_id,
                    'horseName' => $row->horse_name,
                    'horseSlug' => $row->horse_slug,
                    'horseLatestResults' => $row->horse_latest_results,
                    'horseAddedOn' => $row->added_on,
                    'meetings' => $horseMeetings
                ];
            }
        }

        return $this->render('horse.html.twig', [
            'horseDetails' => $horseDetails[0]
        ]);
    }
}