<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use App\Service\Algorithm\AlgorithmDebugLogger;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use Exception;
use mysqli;

class UpdateRatingForRaceTask extends AbstractMySQLTask implements Task
{
    /**
     * @var object
     */
    protected $data;

    /**
     * @var AlgorithmStrategyInterface
     */
    protected $algorithm;

    protected $debugLogger;

    protected $positionPercentage;

    public function __construct(AlgorithmStrategyInterface $algorithm, object $race, float $positionPercentage)
    {
        parent::__construct();

        $this->data = $race;
        $this->positionPercentage = $positionPercentage;
        $this->algorithm = $algorithm;
        $this->debugLogger = new AlgorithmDebugLogger();
    }

    public function run(Environment $environment): bool
    {
        $algStart = microtime(true);
        $mysqli = $this->initMultiSessionDatabase();

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data->race_id, __FILE__);

        try {
            $this->updateRatingsAndH2HForRace($this->data, $mysqli);
        } catch (Exception $e) {
            $this->logger->log("Ratings for race " . $this->data->race_id . " not done. Cause: " . $e->getMessage(), __FILE__);
        }

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data->race_id . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return true;
    }

    /**
     * @throws Exception
     */
    private function updateRatingsAndH2HForRace(object $race, mysqli $mysqli)
    {
        $raceId = $race->race_id;
        // Rating
        if ($raceId) {
            $q = "SELECT * FROM `tbl_hist_results` WHERE `rating`='0' AND `race_id`='$raceId'";
        } else {
            throw new Exception("No race ID found!");
        }

        // THIS IS TRUE CALCULATION OF RATING
        $results = $mysqli->query($q);
        if ($results->num_rows > 0) {
            while ($row = $results->fetch_object()) {
                if ($row->avgsectional != "0" || $row->rank != "0") {
                    // how avg sectional and rank are calculated?!
                    $ratePos = $row->avgsectional + $row->rank;
                    // modify by Jfrost
                    $h2hPoint = (float)$this->algorithm->getH2HPoint($row->horse_h2h);
                    $rating = $ratePos + $h2hPoint;
                    $q = "UPDATE `tbl_hist_results` 
                                 SET `rating` = '$rating' , `temp_h2h` = " . $row->horse_h2h . "
                                 WHERE `hist_id` = '$row->hist_id';";

                    $this->debugLogger->varLog([
                        'ACTION' => "AlgorithmStrategyInterface::getH2HPoint()",
                        'HORSE' => $row->horse_id,
                        'RACE' => $race->race_id,
                        'RACE_DISTANCE' => $row->race_distance,
                        'HISTID' => $row->hist_id,
                        'RATING' => $rating,
                        "H2HPOINT" => $h2hPoint,
                        "H2HHORSE" => $row->horse_h2h,
                        "RATEPOS" => $ratePos,
                        "AVGSECTIONAL" => $row->avgsectional,
                        "ROWRANK" => $row->rank
                    ]);

                    $mysqli->query($q);
                }
            }
        }
    }
}