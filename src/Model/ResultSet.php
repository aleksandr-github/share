<?php

namespace App\Model;

abstract class ResultSet
{
    /**
     * @var float
     */
    protected $totalLoss;

    /**
     * @var float
     */
    protected $totalProfit;

    /**
     * @var array
     */
    protected $results;
    /**
     * @var bool
     */
    protected $isProfitable;

    public function __construct()
    {
        $this->results = [];
        $this->totalLoss = 0;
        $this->totalProfit = 0;
    }

    public function addResult($result)
    {
        $this->results[] = $result;
    }

    /**
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return float
     */
    public function getTotalLoss(): float
    {
        return $this->totalLoss;
    }

    /**
     * @param float $totalLoss
     */
    public function setTotalLoss(float $totalLoss): void
    {
        $this->totalLoss = abs($totalLoss);
    }

    /**
     * @param float $totalLoss
     */
    public function addTotalLoss(float $totalLoss): void
    {
        $this->totalLoss = $this->totalLoss + abs($totalLoss);
    }

    /**
     * @return float
     */
    public function getTotalProfit(): float
    {
        return $this->totalProfit;
    }

    /**
     * @param float $totalProfit
     */
    public function setTotalProfit(float $totalProfit): void
    {
        $this->totalProfit = abs($totalProfit);
    }

    /**
     * @param float $partialProfit
     */
    public function addTotalProfit(float $partialProfit): void
    {
        $this->totalProfit = $this->totalProfit + abs($partialProfit);
    }

    /**
     * @param array $results
     */
    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    /**
     * @return float
     */
    public function getAbsoluteTotal(): float
    {
        if ($this->totalProfit > $this->totalLoss) {
            $this->isProfitable = true;

            return $this->totalProfit - $this->totalLoss;
        } elseif ($this->totalProfit == $this->totalLoss) {
            $this->isProfitable = false;

            return 0;
        } else {
            $this->isProfitable = false;

            return $this->totalProfit - $this->totalLoss;
        }
    }

    /**
     * @param float $profit
     */
    public function calculateAbsoluteTotal(float $profit)
    {
        if ($profit > 0) {
            // it's a profit
            $this->addTotalProfit($profit);
        } else {
            // it's a loss
            $this->addTotalLoss($profit);
        }
    }
}