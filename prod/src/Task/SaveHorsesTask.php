<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use App\Helper\HorseSlugHelper;
use App\Service\Algorithm\AlgorithmDebugLogger;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use Exception;

class SaveHorsesTask extends AbstractMySQLTask implements Task
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var AlgorithmStrategyInterface
     */
    protected $algorithm;

    protected $debugLogger;

    public function __construct(AlgorithmStrategyInterface $algorithm, array $horse)
    {
        parent::__construct();

        $this->algorithm = $algorithm;
        $this->data = $horse;
        $this->debugLogger = new AlgorithmDebugLogger();
    }

    /**
     * todo multiquery
     *
     * @param Environment $environment
     * @return int|string
     * @throws Exception
     */
    public function run(Environment $environment)
    {
        $algStart = microtime(true);
        $mysqli = $this->initMultiSessionDatabase();

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data["name"] . "@" . $this->data["field_id"], __FILE__);

        $horse_id = 0;
        $horseSlug = HorseSlugHelper::generate($this->data["horse_name"]);
        $today_date = date("Y-m-d H:i:s");
        $stmt = $mysqli->prepare("SELECT horse_id FROM tbl_horses WHERE horse_slug = ? LIMIT 1");
        $stmt->bind_param("s", $horseSlug);
        if ($stmt->execute()) {
            $stmt->bind_result($horse_id);
            while ($stmt->fetch()) {
                $horse_id = $horse_id;
            }
            $stmt->close();
        } else {
            $msg = "[" . date("Y-m-d H:i:s") . "] Select horse_id failed";
            $this->logger->log($msg, __FILE__);
        }

        if ($horse_id) {
            $action_now = 'updated';

            //adding temp races
            $tempRaceHorseData = [
                'ACTION' => 'DefaultAlgorithmInterface::insertTempHRaces()',
                'RACE_ID' => $this->data["race_id"],
                'HORSE_ID' => $horse_id,
                'HORSE_NUMBER' => $this->data["horse_number"],
                'FIXED_ODDS' => $this->data["horse_fixed_odds"],
                'H2H' => $this->data["horse_h2h"],
                'WEIGHT' => $this->data["horse_weight"],
                'HORSE_WIN' => $this->data["horse_win"],
                'PLC' => $this->data["horse_plc"],
                'AVG' => $this->data["horse_avg"]
            ];
            $this->debugLogger->varLog($tempRaceHorseData);

            $stathra = $mysqli->prepare("INSERT INTO `tbl_temp_hraces` (`race_id`, `horse_id`, `horse_num`, `horse_fxodds`, `horse_h2h`, `horse_weight`, `horse_win`, `horse_plc`, `horse_avg`) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ? );");
            $stathra->bind_param("sssssssss", $this->data["race_id"], $horse_id, $this->data["horse_number"], $this->data["horse_fixed_odds"], $this->data["horse_h2h"], $this->data["horse_weight"], $this->data["horse_win"], $this->data["horse_plc"], $this->data["horse_avg"]);
            $stathra->execute();
            $stathra->close();
            // end temp races
        }

        $sql = "INSERT INTO `tbl_horses` (`horse_name`, `horse_slug`, `horse_latest_results`, `added_on` ) VALUES ( ?, ?, ?, ? );";

        if (!($mysqliStatement = $mysqli->prepare($sql))) {
            $msg = "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
            $this->logger->log($msg, __FILE__);
            throw new Exception($msg);
        }

        $tempRaceHorseData = [
            'ACTION' => 'DefaultAlgorithmInterface::saveHorse()',
            'HORSE_ID' => $horse_id,
            'HORSE_NAME' => $this->data["horse_name"],
            'LATEST_RESULT' => $this->data["horse_latest_results"]
        ];
        $this->debugLogger->varLog($tempRaceHorseData);

        $mysqliStatement->bind_param("ssss", $this->data["horse_name"], $horseSlug, $this->data["horse_latest_results"], $today_date);
        if (!$mysqliStatement->execute()) {
            $msg = "[" . date("Y-m-d H:i:s") . "] Insert failed: " . $mysqliStatement->error;
            $this->logger->log($msg, __FILE__);
        }

        // this happens only when horse has been added
        $horseInsertId = $mysqli->insert_id;
        if ($horseInsertId) {
            $horse_id_n = $mysqli->insert_id;
            $action_now = 'added';
            if(empty($this->data["horse_fixed_odds"])) {
                $horse_fixed_odds = '0';
            }
            else {
                $horse_fixed_odds = $this->data["horse_fixed_odds"];
            }

            if(empty($this->data["horse_h2h"])) {
                $horse_h2hnow = '0';
            }
            else {
                $horse_h2hnow = $this->data["horse_h2h"];
            }

            if(empty($this->data["horse_number"])) {
                $horse_cnumber = '0';
            }
            else {
                $horse_cnumber = $this->data["horse_number"];
            }

            //adding temp races
            $stathra = $mysqli->prepare("INSERT INTO `tbl_temp_hraces` (`race_id`, `horse_id`, `horse_num`, `horse_fxodds`, `horse_h2h`, `horse_weight`, `horse_win`, `horse_plc`, `horse_avg` ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ? );");
            $stathra->bind_param("sssssssss", $this->data["race_id"], $horse_id_n, $this->data["horse_number"], $horse_fixed_odds, $horse_h2hnow, $this->data["horse_weight"], $this->data["horse_win"], $this->data["horse_plc"], $this->data["horse_avg"]);
            $stathra->execute();
            $stathra->close();
            // end temp races
        }
        
        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data["name"] . "@" . $this->data["field_id"] . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return $horseInsertId;
    }
}