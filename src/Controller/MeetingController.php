<?php

namespace App\Controller;

use App\Service\DBConnector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MeetingController extends AbstractController
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
     * @Route("/meeting", name="meeting_index")
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $mysqli = $this->dbConnector->getDbConnection();

        if(!empty($request->query->get('date'))){
            $sql = "SELECT * FROM `tbl_meetings` WHERE `meeting_date`='" . $request->query->get('date') . "'";
        }
        else {
            $sql = "SELECT * FROM `tbl_meetings`";
        }
        $meetings = $mysqli->query($sql);

        $meetingsArray = [];
        if ($meetings->num_rows > 0) {
            // output data of each row
            while ($row = $meetings->fetch_object()) {
                $meetingsArray[] = [
                    'meetingId' => $row->meeting_id,
                    'meetingName' => $row->meeting_name,
                    'meetingDate' => $row->meeting_date
                ];
            }
        }
        $mysqli->close();

        return $this->render('meeting.html.twig', [
            'meetings' => $meetingsArray
        ]);
    }
}