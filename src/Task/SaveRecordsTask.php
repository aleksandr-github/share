<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use App\Service\Algorithm\AlgorithmDebugLogger;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use Exception;
use mysqli;

class SaveRecordsTask extends AbstractMySQLTask implements Task
{
    /**
     * @var array
     *
     * Array
    (
    [horse_id] => 935046
    [name] => Grinzinger Prince
    [race_date] => 24/10/20
    [race_name] => Inglis Banner
    [track] => MOV
    [track_name] => Moonee Valley
    [distance] => 1000
    [pos] => 8
    [mrg] => 4.4
    [condition] => S7
    [weight] => 58.0
    [prize] => 10k/500k
    [time] => 1.02
    [sectional] => 600/36.82
    [time2] => 1.20
    )

     */
    protected $data;

    /**
     * @var AlgorithmStrategyInterface
     */
    protected $algorithm;

    /**
     * @var AlgorithmDebugLogger
     */
    protected $debugLogger;

    public function __construct(AlgorithmStrategyInterface $algorithm, array $record)
    {
        parent::__construct();

        $this->debugLogger = new AlgorithmDebugLogger();
        $this->algorithm = $algorithm;
        $this->data = $record;
    }

    /**
     * @param Environment $environment
     * @return string
     * @throws Exception
     */
    public function run(Environment $environment): string
    {
        $algStart = microtime(true);
        $mysqli = $this->initMultiSessionDatabase();

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data["name"] . "@" . $this->data["race_name"], __FILE__);

        // FROM HERE
        // Modify time2 to suit our needs
        $this->data['original_distance'] = $this->data['distance'];
        $place = $this->getPlaceForRecord();
        if ($place != 1) {
            $this->algorithm->processTimeForRecord($this->data);
        } else {
            // First place, leave it as it is
            $this->data['time2'] = $this->data['time'];
        }

        $initialDistance = $this->data["distance"];
        $thousands =  intval($initialDistance/1000);
        $thousandsModule = $initialDistance%1000;
        $hundrends = intval($thousandsModule/100);
        $tens = $initialDistance/10;

        if ($thousands < 1) {
            $this->data["distance"] = ($hundrends * 100);
        } else {
            $this->data["distance"] = ($thousands * 1000) + ($hundrends * 100);
        }

        $this->data["handicap"] = 0.00;

        $raced = explode('/', $this->data["race_date"]);
        $raceD = $raced[0];
        $raceM = $raced[1];
        $raceY = '20'.$raced[2];
        $racefulldate = $raceY.'-'.$raceM.'-'.$raceD;
        $rankorrat = '0.00';

        // TODO this is quite unefficient and can be done better
        $raceidnow = $this->getRaceDetailsForOldRaceId($this->data["race_old_id"], $mysqli);
        $horse_id_now = $this->getHorseDetailsForHorseName($this->data["name"], $mysqli);
        $fixed_odds = $this->generateTempRcDataForValue($horse_id_now, $raceidnow, 'horse_fxodds', $mysqli);
        $horse_h2h = $this->generateTempRcDataForValue($horse_id_now, $raceidnow, 'horse_h2h', $mysqli);
        $horse_numb = $this->generateTempRcDataForValue($horse_id_now, $raceidnow, 'horse_num', $mysqli);

        $arrayOfInsert = [
            $raceidnow,
            $racefulldate,
            $this->data["distance"],
            $horse_id_now,
            $horse_numb,
            $this->data["pos"],
            $this->data["weight"],
            $fixed_odds,
            $horse_h2h,
            $this->data["prize"],
            $this->data["time"],
            $this->data["mrg"],
            $this->data["sectional"],
            $this->data["handicap"],
            $rankorrat,
            $rankorrat
        ];

        $unprepared = "INSERT INTO `tbl_hist_results` (`race_id`, `race_date`, `race_distance`, `horse_id`, `h_num`, `horse_position`, `horse_weight`, `horse_fixed_odds`, `horse_h2h`, `prize`, `race_time`, `length`, `sectional`, `handicap`, `rating`, `rank`) VALUES ";
        $sql = vsprintf("(%s, \"%s\", \"%s\", %s, \"%s\", %s, \"%s\", \"%s\", \"%s\", \"%s\", \"%s\", %s, \"%s\", %s, %s, %s); ", $arrayOfInsert);
        $finishedQuery = $unprepared . $sql;

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data["name"] . "@" . $this->data["race_name"] . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return $finishedQuery;
    }

    /**
     * @return int
     */
    protected function getPlaceForRecord(): int
    {
        try {
            $pos = explode('/', $this->data['pos']);

            return intval($pos[0]);
        } catch (Exception $e) {
            $this->logger->log($e->getMessage(), __FILE__);
        }

        return 0;
    }

    /**
     * @throws \Exception
     */
    protected function getRaceDetailsForOldRaceId($raceoldnum, mysqli $mysqli)
    {
        $race_id = 0;
        $stmt = $mysqli->prepare("SELECT race_id FROM `tbl_races` WHERE old_race_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $raceoldnum);

            if ($stmt->execute()) {
                $stmt->bind_result($race_id);
                while ($stmt->fetch()) {
                    $race_id = $race_id;
                }
                $stmt->close();
            }
            if ($race_id) {
                return $race_id;
            }
        } else {
            // bad fallback
            $this->logger->log('Falling back to default SQL, pinging...', __FILE__);
            if ($mysqli->ping()) {
                $sql = "SELECT race_id FROM `tbl_races` WHERE old_race_id = " . $raceoldnum . " LIMIT 1";
                $query = $mysqli->query($sql);
                while ($row = $query->fetch_assoc()) {
                    $race_id = $row['race_id'];
                }

                return $race_id;
            } else {
                $this->logger->log('Pinging DB failed. Critical error, mysqli connection is lost. Consider tuning down mysqli workers number.', __FILE__);

                throw new Exception('Pinging DB failed. Critical error, mysqli connection is lost. Consider tuning down mysqli workers number.');
            }
        }

        return null;
    }

    /**
     * @param $horsename
     * @param $mysqli
     * @return int|mixed
     */
    protected function getHorseDetailsForHorseName($horsename, $mysqli)
    {
        $horse_id = 0;
        $horseslug = preg_replace('/[^A-Za-z0-9\-]/', '', strtolower($horsename));
        $stmt = $mysqli->prepare("SELECT horse_id FROM `tbl_horses` WHERE horse_slug = ?");
        $stmt->bind_param("s", $horseslug);

        if ($stmt->execute()) {
            $stmt->bind_result($horse_id);
            while ($stmt->fetch()) {
                $horse_id = $horse_id;
            }
            $stmt->close();
        }
        if ($horse_id) {
            return $horse_id;
        }

        return null;
    }

    /**
     * @param $horseid
     * @param $raceid
     * @param $reqvalue
     * @param $mysqli
     * @return mixed|string
     */
    private function generateTempRcDataForValue($horseid, $raceid, $reqvalue, $mysqli)
    {
        $requ_val = '';
        $stmt = $mysqli->prepare("SELECT $reqvalue FROM `tbl_temp_hraces` WHERE horse_id ='$horseid' AND race_id = '$raceid'");

        if ($stmt->execute()) {
            $stmt->bind_result($requ_val);
            while ($stmt->fetch()) {
                $requ_val = $requ_val;
            }
            $stmt->close();
        }
        if ($requ_val) {
            return $requ_val;
        }

        return null;
    }
}