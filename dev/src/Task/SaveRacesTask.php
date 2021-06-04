<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Exception;
use Throwable;

class SaveRacesTask extends AbstractMySQLTask implements Task
{
    /**
     * @var array
     */
    protected $data;

    protected $base_url = "https://www.racingzone.com.au";

    public function __construct(array $race)
    {
        parent::__construct();

        $this->data = $race;
    }

    /**
     * TODO multiquery
     *
     * @param Environment $environment
     * @return int|mixed|string
     * @throws Exception|\Throwable
     */
    public function run(Environment $environment)
    {
        $algStart = microtime(true);
        try {
            $mysqli = $this->initMultiSessionDatabase();
        } catch (Throwable $e) {
            throw $e;
        }

        try {
            $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data["title"] . "@" . $this->data["schedule_time"], __FILE__);

            $race_id = 0;
            $rank_status = 0;
            $sql = "SELECT race_id FROM tbl_races WHERE meeting_id = ? AND race_order = ? AND race_schedule_time = ? AND race_title = ? AND race_distance = ? LIMIT 1";
            if (!$stmt = $mysqli->prepare($sql)) {
                $this->logger->log('[ERROR] Statement failed', __FILE__);
            }
            $stmt->bind_param("sssss", $this->data["meeting_id"], $this->data["number"], $this->data["schedule_time"], $this->data["title"], $this->data["distance"]);
            if ($stmt->execute()) {
                $stmt->bind_result($race_id);
                while ($stmt->fetch()) {
                    $race_id = $race_id;
                }
                $stmt->close();
            } else {
                $this->logger->log("{FAIL} Select race_id: " . $this->data["title"] . "@" . $this->data["schedule_time"] . " failed", __FILE__);
            }
            if ($race_id) {
                return $race_id;
            }

            $raceslug = preg_replace('/[^A-Za-z0-9\-]/', '', strtolower($this->data["title"]));

            $url = str_replace("http://www.racingzone.com.au/", "", $this->data["url"]);
            $url = str_replace($this->base_url, "", $this->data["url"]);
            $end = explode('/', $url);
            $cont = count($end) - 2;
            $last_url = $end[$cont];
            $oldidnum = explode('-', $last_url);

            $distance = $this->data["distance"];
            if ($distance % 10 < 5) {
                $distance -= $distance % 10;
            } else {
                $distance += (10 - ($distance % 10));
            }

            if ($distance % 100 < 50) {
                $round_difference = $distance % 100;
                $round_distance = $distance - $round_difference;
            } else {
                $round_difference = (100 - ($distance % 100));
                $round_distance = $distance + $round_difference;
            }

            $sql = "INSERT INTO `tbl_races` (`old_race_id`, `meeting_id`, `race_order`, `race_schedule_time`, `race_title`, `race_slug`, `race_distance`, `round_distance`, `race_url`, `rank_status`, `sec_status`) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? );";
            if (!($mysqliStatement = $mysqli->prepare($sql))) {
                $this->logger->log("{FAIL} Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error, __FILE__);
            }

            $mysqliStatement->bind_param("sssssssssss", $oldidnum[0], $this->data["meeting_id"], $this->data["number"], $this->data["schedule_time"], $this->data["title"], $raceslug, $distance, $round_distance, $this->data["url"], $rank_status, $rank_status);
            if (!$mysqliStatement->execute()) {
                $this->logger->log("{FAIL} Insert failed for: " . $oldidnum[0] . " / " . $this->data["meeting_id"] . " / " . $this->data["number"] . " / " . $this->data["schedule_time"] . " / " . $this->data["title"] . " / " . $raceslug . " / " . $distance . " / " . $round_distance . " / " . $this->data["url"] . " / " . $rank_status . " / " . $rank_status, __FILE__);
            }

            $time_elapsed_secs = microtime(true) - $algStart;
            $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data["title"] . "@" . $this->data["schedule_time"] . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);
        } catch (Throwable $t) {
            $this->logger->log($t->getMessage(), __FILE__);
        }

        $lastId = $mysqli->insert_id;
        //$mysqli->close();

        return $lastId;
    }
}