<?php

namespace App\Service\Algorithm;

use App\Enum\OrderEnum;
use App\Model\AlgorithmInfo;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;

class DefaultAlgorithm implements AlgorithmStrategyInterface
{
    protected $isDebug = false;
    protected $isAvailable = true;
    protected $isActive = true;
    protected $priority = 10;

    protected $logger;

    public function setLogger(AlgorithmDebugLogger $logger)
    {
        $this->logger = $logger;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getAlgorithmInfo(): AlgorithmInfo
    {
        return new AlgorithmInfo(
            '0.3.0',
            'Default Algorithm',
            self::class,
            "Default algorithm without secpoint, with multiple races-for-horses support"
        );
    }

    /**
     * Used in:
     *  - src/Task/UpdateHandicapForRaceTask.php
     *
     * Purpose:
     *
     * TODO DESCRIPTION WHAT IT DOES
     *
     * @param $value
     * @param $array
     * @param int $order
     * @return float|int|null
     */
    public function generateRank($value, $array, $order = OrderEnum::NORMAL)
    {
        // sort
        if ($order == OrderEnum::NORMAL) {
            sort($array);
        } else {
            rsort($array);
        }

        // add item for counting from 1 but 0
        array_unshift($array, $value + 1);

        // select all indexes with the value
        $keys = array_keys($array, $value);
        if (count($keys) == 0) {
            return null;
        }

        // calculate the rank
        $res = array_sum($keys) / count($keys);

        return $res / 2;
    }

    /**
     * Used in:
     *  - src/Task/UpdateHandicapForRaceTask.php
     *
     * Purpose:
     *
     * TODO DESCRIPTION WHAT IT DOES
     *
     * @param $value
     * @param $array
     * @param int $order
     * @return float|int|null
     */
    public function generateAVGSectional($value, $array, $order = OrderEnum::NORMAL)
    {
        // sort
        if ($order == OrderEnum::NORMAL) {
            sort($array);
        } else {
            rsort($array);
        }

        // add item for counting from 1 but 0
        array_unshift($array, $value + 1);

        // select all indexes with the value
        $keys = array_keys($array, $value);
        if (count($keys) == 0) {
            return null;
        }

        // calculate the rank
        $res = array_sum($keys) / count($keys);

        return $res / 2;
    }

    /**
     * Used in:
     *  - src/Task/UpdateHandicapForRaceTask.php
     *
     * Purpose:
     *
     * TODO DESCRIPTION WHAT IT DOES
     *
     * @param string $h2h
     * @return string
     */
    public function getH2HPoint(string $h2h): string
    {
        if (isset(explode("-", $h2h)[1])) {
            $h2h_ = intval(explode("-", $h2h)[0]) - intval(explode("-", $h2h)[1]);
        } else {
            $h2h_ = 0;
        }

        return number_format($h2h_ / 2, 1);
    }


    /**
     * Used in:
     *  - src/Task/UpdateDistanceForRaceTask.php
     *
     * Purpose:
     *
     * TODO DESCRIPTION WHAT IT DOES
     *
     * @param $value
     * @param $array
     * @param int $order
     * @return false|int|string
     */
    public function distanceNewRank($value, $array, $order = OrderEnum::NORMAL)
    {
        $array = array_unique($array);

        if ($order == OrderEnum::NORMAL) {
            sort($array);
        } else {
            rsort($array);
        }

        return array_search($value, $array) + 1;
    }

    /**
     * Used in:
     *  - src/Task/ResetHandicapForHistoricResultTask.php
     *
     * Purpose:
     *
     *  - creates new handicap value
     *
     * @param $length
     * @param $distance
     * @param $orgdistance
     * @param $horsePosition
     * @param $time
     * @param $modifier
     * @param $timer
     * @return float|int
     */
    public function generateHandicap($length, $distance, $orgdistance, $horsePosition, $time, $modifier, $timer)
    {
        //Getting the position of the horse
        $horsePosition = explode('/', $horsePosition);
        $position = intval($horsePosition[0]);
        $remainder = $this->getRemainingDistance($distance);

        if ($position == 1) {
            if ($distance < $orgdistance) {
                $newtime = $this->roundWinUp($time, $remainder, $timer);
            } else {
                $newtime = $this->roundWinDown($time, $remainder, $timer);
            }
        } else {
            if ($distance < $orgdistance) {
                $newtime = $this->roundLoseUp(
                    $time,
                    $length,
                    $modifier,
                    $remainder,
                    $timer
                );
            } else {
                if ($distance > $orgdistance) {
                    $newtime = $this->roundLoseDown(
                        $time,
                        $length,
                        $modifier,
                        $remainder,
                        $timer
                    );
                } else {
                    $newtime = $time + ($length * $modifier);
                }
            }
        }

        return $newtime;
    }

    /**
     * @param array $data
     */
    public function processTimeForRecord(array &$data)
    {
        $this->debug(
            sprintf("[PROCESS_TIME_RECORD] %s", $data['distance'])
        );

//    	First round up distance to nearest 10
//    	i.e 833 = 830
//		Then we round off again to nearest 100.
//		830-800 (with a remainder of 30)
//		Then we will use use an algorithm below to determine how much time to
//		add to the final time.
        $distance = $data["distance"];
        if ($distance % 10 < 5)
        {
            $distance -= $distance % 10;
        }
        else
        {
            $distance += (10 - ($distance % 10));
        }
        $sign = 1.0;
        if ($distance % 100 < 50)
        {
            $reminder_distance = $distance % 100;
            $distance -= $reminder_distance;
            $sign = -1.0;
        }
        else
        {
            $reminder_distance = (100 - ($distance % 100));
            $distance += $reminder_distance;
        }
        $offset = $this->getOffsetForValue($distance);
        $data['distance'] = $distance;
        $data['time2'] = $sign * $reminder_distance * 0.01 * $offset + $data['time2'];
    }

    /**
     * @param $distance
     * @return int
     */
    protected function getRemainingDistance($distance): int
    {
        $this->debug(
            sprintf("[GET_REMAINING_DISTANCE] %s", $distance)
        );

        if ($distance % 10 < 5) {
            $distance -= $distance % 10;
        } else {
            $distance += (10 - ($distance % 10));
        }

        if ($distance % 100 < 50) {
            $reminder_distance = $distance % 100;
        } else {
            $reminder_distance = (100 - ($distance % 100));
        }

        return $reminder_distance;
    }

    /**
     * @param $time
     * @param $remainder
     * @param $timer
     * @return float|int
     */
    protected function roundWinUp($time, $remainder, $timer)
    {
        $this->debug(
            sprintf("[ROUND_WIN_UP] %s %s %s", $time, $remainder, $timer)
        );

        return $time + ($timer * $remainder);
    }

    /**
     * @param $time
     * @param $remainder
     * @param $timer
     * @return float|int
     */
    protected function roundWinDown($time, $remainder, $timer)
    {
        $this->debug(
            sprintf("[ROUND_WIN_DOWN] %s %s %s", $time, $remainder, $timer)
        );

        return $time - ($timer * $remainder);
    }

    /**
     * if horse loses
     * @param $time
     * @param $length
     * @param $modifier
     * @param $remainder
     * @param $timer
     * @return float|int
     */
    protected function roundLoseUp($time, $length, $modifier, $remainder, $timer)
    {
        $this->debug(
            sprintf("[ROUND_LOSE_UP] %s %s %s %s %s", $time, $length, $modifier, $remainder, $timer)
        );

        return $time + ($length * $modifier) + ($timer * $remainder);
    }

    /**
     * @param $time
     * @param $length
     * @param $modifier
     * @param $remainder
     * @param $timer
     * @return float|int
     */
    protected function roundLoseDown($time, $length, $modifier, $remainder, $timer)
    {
        $this->debug(
            sprintf("[ROUND_LOSE_DOWN] %s %s %s %s %s", $time, $length, $modifier, $remainder, $timer)
        );

        return $time + ($length * $modifier) - ($timer * $remainder);
    }

    /**
     * @param $val
     * @return float
     */
    protected function getOffsetForValue($val): float
    {
        $this->debug(
            sprintf("[GET_OFFSET_FOR_VALUE] %s", $val)
        );

//    	800 -> 899 = 0.77 (10 sec)
//		900 -> 999 = 0.87 (10 sec)
//		1000 -> 1099 = 0.97 (5 sec)
//		1100 -> 1199 = 1.02 (7 sec)
//		10/ 10 - 1 sec per 10 metres)
//		5 / 10 = 0.5 sec per 10 metres)
//		7 / 10 = 0.7 sec per 10 metres)
        if ($val >= 800 AND $val <= 999) {
            return 1;
        } elseif ($val >= 1000 AND $val <= 1099) {
            return 0.5;
        } elseif ($val >= 1100 AND $val <= 4000) {
            return 0.7;
        }

        return 0;
    }

    /**
     * Used as logger for all algorithm events
     *
     * @param string $message
     */
    protected function debug(string $message)
    {
        if ($this->isDebug) {
            $this->logger->log($message);
        }
    }
}