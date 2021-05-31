<?php

namespace App\Service\Algorithm\Strategy;

use App\Enum\OrderEnum;
use App\Model\AlgorithmInfo;

interface AlgorithmStrategyInterface
{
    public function isAvailable(): bool;
    public function isActive(): bool;
    public function getAlgorithmInfo(): AlgorithmInfo;
    public function generateRank($value, $array, $order = OrderEnum::NORMAL);
    public function generateAVGSectional($value, $array, $order = OrderEnum::NORMAL);
    public function getH2HPoint(string $h2h): string;
    public function distanceNewRank($value, $array, $order = OrderEnum::NORMAL);
    public function generateHandicap($length, $distance, $orgdistance, $horsePosition, $time, $modifier, $timer);
    public function processTimeForRecord(array &$data);
}