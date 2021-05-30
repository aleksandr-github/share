<?php

namespace App\Service\Algorithm\Strategy;

class AlgorithmContext
{
    /**
     * @var AlgorithmStrategyInterface[]
     */
    private $strategies = [];

    public function addStrategy(AlgorithmStrategyInterface $algorithmStrategy)
    {
        $this->strategies[] = $algorithmStrategy;
    }

    public function getAlgorithm(): AlgorithmStrategyInterface
    {
        $enabledStrategies = [];

        foreach ($this->strategies as $strategy) {
            if ($strategy->isActive() && $strategy->isAvailable()) {
                $enabledStrategies[$strategy->getPriority()] = $strategy;
            }
        }

        if (count($enabledStrategies) > 0) {
            ksort($enabledStrategies);

            return $enabledStrategies[array_key_first($enabledStrategies)];
        }

        throw new \LogicException("No strategy set up to handle algorithm data.");
    }
}