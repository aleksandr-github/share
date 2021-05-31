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
        foreach ($this->strategies as $strategy) {
            // first active and available strategy will parse data
            if ($strategy->isActive() && $strategy->isAvailable()) {
                return $strategy;
            }
        }

        throw new \LogicException("No strategy set up to handle algorithm data.");
    }
}