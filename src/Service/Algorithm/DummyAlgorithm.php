<?php

namespace App\Service\Algorithm;

use App\Enum\OrderEnum;
use App\Model\AlgorithmInfo;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;

class DummyAlgorithm implements AlgorithmStrategyInterface
{
    protected $isDebug = false;
    protected $isAvailable = true;
    protected $isActive = true;
    protected $priority = 99;
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
            '0.0.1',
            'Dummy Algorithm',
            self::class,
            "Skeleton of an algorithm functions"
        );
    }

    public function generateRank($value, $array, $order = OrderEnum::NORMAL)
    {
        // implement me

        return random_int(1, 99);
    }

    public function generateAVGSectional($value, $array, $order = OrderEnum::NORMAL)
    {
        // implement me

        return random_int(1, 99);
    }

    public function getH2HPoint(string $h2h): string
    {
        return (string)random_int(1, 99);
    }

    public function distanceNewRank($value, $array, $order = OrderEnum::NORMAL)
    {
        return (string)random_int(1, 99);
    }

    public function generateHandicap($length, $distance, $orgdistance, $horsePosition, $time, $modifier, $timer)
    {
        return (string)random_int(1, 99);
    }

    public function processTimeForRecord(array &$data)
    {
        return (string)random_int(1, 99);
    }
}