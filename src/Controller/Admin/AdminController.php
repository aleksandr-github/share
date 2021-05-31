<?php

namespace App\Controller\Admin;

use App\Service\DBConnector;
use App\Service\PrettyLogger;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    /**
     * @var DBConnector
     */
    protected $db;

    public function __construct()
    {
        $this->db = new DBConnector();
    }

    /**
     * @Route("/admin/truncate_tables", name="admin_truncate_tables")
     *
     * @return JsonResponse
     */
    public function truncateTables(Request $request): JsonResponse
    {
        if ($request->query->get('key') != 'P@ssw0rd') {
            throw new UnauthorizedHttpException('simple', 'Bad key provided');
        }

        $mysqli = $this->db->getDbConnection();

        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }

        $sql = "TRUNCATE `tbl_hist_results`";
        $mysqli->query($sql);
        $sql = "TRUNCATE `tbl_horses`";
        $mysqli->query($sql);
        $sql = "TRUNCATE `tbl_meetings`";
        $mysqli->query($sql);
        $sql = "TRUNCATE `tbl_races`";
        $mysqli->query($sql);
        $sql = "TRUNCATE `tbl_results`";
        $mysqli->query($sql);
        $sql = "TRUNCATE `tbl_temp_hraces`";
        $mysqli->query($sql);

        return new JsonResponse([
            'code' => 200,
            'message' => 'Tables truncated'
        ]);
    }

    /**
     * @Route("/admin/truncate_logs", name="admin_truncate_logs")
     *
     * @return JsonResponse
     */
    public function truncateLogs(Request $request): JsonResponse
    {
        if ($request->query->get('key') != 'P@ssw0rd') {
            throw new UnauthorizedHttpException('simple', 'Bad key provided');
        }

        $files = [
            APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'results_log.txt',
            APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'main_log.txt',
            APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'main_logs.txt',
            APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'db_logs.txt',
            APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'race_details.txt',
        ];

        foreach ($files as $file) {
            file_put_contents($file, "");
        }

        return new JsonResponse([
            'code' => 200,
            'message' => 'Log files truncated and set up.'
        ]);
    }
}