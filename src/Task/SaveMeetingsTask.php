<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Exception;

class SaveMeetingsTask extends AbstractMySQLTask implements Task
{
    /**
     * @var array
     */
    protected $data;

    public function __construct(array $meeting)
    {
        parent::__construct();

        $this->data = $meeting;
    }

    /**
     * @param Environment $environment
     * @throws Exception
     */
    public function run(Environment $environment)
    {
        $algStart = microtime(true);
        $mysqli = $this->initMultiSessionDatabase();

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data["place"], __FILE__);
        $today_date = date("Y-m-d H:i:s");
        $meeting_id = 0;
        $stmt = $mysqli->prepare("SELECT meeting_id FROM tbl_meetings WHERE meeting_date = ? AND meeting_name = ? LIMIT 1");
        $stmt->bind_param("ss", $this->data["date"], $this->data["place"]);
        if ($stmt->execute()) {
            $stmt->bind_result($meeting_id);
            while ($stmt->fetch()) {
                $meeting_id = $meeting_id;
            }
            $stmt->close();
        } else {
            $this->logger->log("[" . date("Y-m-d H:i:s") . "] Select meeting_id failed", __FILE__);
        }
        if ($meeting_id) {
            return $meeting_id;
        }

        $sql = "INSERT INTO `tbl_meetings` ( `meeting_date`, `meeting_name`, `meeting_url`, `added_on` ) VALUES ( ?, ?, ?, ?);";
        if (!($mysqliQuery = $mysqli->prepare($sql))) {
            $msg = "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
            $this->logger->log($msg, __FILE__);
            throw new Exception($msg);
        }

        $mysqliQuery->bind_param("ssss", $this->data["date"], $this->data["place"], $this->data["url"], $today_date);
        if (!$mysqliQuery->execute()) {
            $msg = "[" . date("Y-m-d H:i:s") . "] Insert failed: " . $mysqliQuery->error;
            $this->logger->log($msg, __FILE__);
            throw new Exception($msg);
        }

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data["place"] . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return $mysqli->insert_id;
    }
}