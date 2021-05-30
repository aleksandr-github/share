<?php

namespace App\Entity;

use App\Repository\AlgorithmRunResultRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AlgorithmRunResultRepository::class)
 */
class AlgorithmRunResult
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="float")
     */
    private $timerHandicapMultiplier;

    /**
     * @ORM\Column(type="integer")
     */
    private $positionPercentage;

    /**
     * @ORM\Column(type="float")
     */
    private $handicapModifier;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $avgRankTotalProfit;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $ratingTotalProfit;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimerHandicapMultiplier(): ?float
    {
        return $this->timerHandicapMultiplier;
    }

    public function setTimerHandicapMultiplier(float $timerHandicapMultiplier): self
    {
        $this->timerHandicapMultiplier = $timerHandicapMultiplier;

        return $this;
    }

    public function getPositionPercentage(): ?int
    {
        return $this->positionPercentage;
    }

    public function setPositionPercentage(int $positionPercentage): self
    {
        $this->positionPercentage = $positionPercentage;

        return $this;
    }

    public function getHandicapModifier(): ?float
    {
        return $this->handicapModifier;
    }

    public function setHandicapModifier(float $handicapModifier): self
    {
        $this->handicapModifier = $handicapModifier;

        return $this;
    }

    public function getAvgRankTotalProfit(): ?string
    {
        return $this->avgRankTotalProfit;
    }

    public function setAvgRankTotalProfit(?string $avgRankTotalProfit): self
    {
        $this->avgRankTotalProfit = $avgRankTotalProfit;

        return $this;
    }

    public function getRatingTotalProfit(): ?string
    {
        return $this->ratingTotalProfit;
    }

    public function setRatingTotalProfit(?string $ratingTotalProfit): self
    {
        $this->ratingTotalProfit = $ratingTotalProfit;

        return $this;
    }
}
