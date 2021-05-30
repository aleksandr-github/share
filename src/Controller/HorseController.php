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
        $mysqli = $this->dbConnector->getDbConnection();
        $sql = "SELECT * FROM `tbl_horses`";
        $result = $mysqli->query($sql);

        $horses = [];
        if ($result->num_rows > 0) {
            // output data of each row
            while ($row = $result->fetch_object()) {
                $horses[] = [
                    'horseId' => $row->horse_id,
                    'horseName' => $row->horse_name,
                    'horseSlug' => $row->horse_slug,
                    'horseLatestResults' => $row->horse_latest_results,
                    'horseAddedOn' =>$row->added_on
                ];
            }
        }
        $mysqli->close();

        return $this->render('horses.html.twig', [
            'horses' => $horses
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