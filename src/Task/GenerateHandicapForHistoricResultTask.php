<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use App\Model\App\HistoricResult;
use App\Service\Algorithm\AlgorithmDebugLogger;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use Exception;
use stdClass;

class GenerateHandicapForHistoricResultTask extends AbstractMySQLTask implements Task
{
    /**
     * @var HistoricResult
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

    protected $timer;
    protected $modifier;

    public function __construct(AlgorithmStrategyInterface $algorithm, HistoricResult $histResult, float $timerHandicapMultiplier, float $handicapModifier)
    {
        parent::__construct();

        $this->debugLogger = new AlgorithmDebugLogger();
        $this->algorithm = $algorithm;
        $this->data = $histResult;
        $this->timer = $_ENV['timerHandicapMultiplier'] ?? $timerHandicapMultiplier;
        $this->modifier = $_ENV['handicapModifier'] ?? $handicapModifier;
    }

    /**
     * @param Environment $environment
     * @return int|string
     * @throws \Exception
     */
    public function run(Environment $environment)
    {
        $algStart = microtime(true);
        $mysqli = $this->initMultiSessionDatabase();

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data->getHistId() . '@' . $this->data->getRaceId() . '#' . $this->data->getHorseId(), __FILE__);

        $raceDetails = $this->getRace($this->data->getRaceId(), $mysqli);
        $distance = round($raceDetails->race_distance / 100);
        $roundedDistance = $distance * 100;
        $newHandicap = $this->algorithm->generateHandicap(
            $this->data->getLength(),
            $raceDetails->race_distance,
            $roundedDistance,
            $this->data->getHorsePosition(),
            number_format($this->data->getRaceTime(), 2),
            $this->modifier,
            $this->timer
        );
        $newHandicap = number_format($newHandicap, 3);

        $this->debugLogger->varLog([
            'ACTION' => "AlgorithmStrategyInterface::generateHandicap()",
            'HORSE' => $this->data->getHorseId(),
            'RACE' => $this->data->getRaceId(),
            'RACE_DISTANCE' => $this->data->getRaceDistance(),
            'HORSE_POSITION' => $this->data->getHorsePosition(),
            'HANDICAP_RESULT' => $newHandicap,
            'LENGTH' => $this->data->getLength(),
            'RACE_DISTANCE_DETAILS_LENGTH' => $raceDetails->race_distance,
            'ROUNDED_DISTANCE' => $roundedDistance,
            'RACE_TIME' => number_format($this->data->getRaceTime(), 2),
            'MODIFIER' => $this->modifier,
            'TIMER' => $this->timer
        ]);

        $id = $this->data->getHistId();
        $sql = "UPDATE `tbl_hist_results` SET `handicap`='$newHandicap' WHERE hist_id = '$id';";
        $stmt = $mysqli->query($sql);
        if (!$stmt) {
            $msg = "Query failed: (" . $mysqli->errno . ") " . $mysqli->error;
            $this->logger->log($msg, __FILE__);

            throw new Exception($msg);
        }

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data->getHistId() . '@' . $this->data->getRaceId() . '#' . $this->data->getHorseId() . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return $mysqli->insert_id;
    }

    /**
     * @param $raceId
     * @param $mysqli
     * @return object|stdClass
     */
    public function getRace($raceId, $mysqli)
    {
        $stmt = $mysqli->query("SELECT * FROM `tbl_races` WHERE `race_id`='$raceId'");

        return $stmt->fetch_object();
    }
}