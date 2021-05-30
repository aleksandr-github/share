<?php

namespace App\Model\App;

class HorseRatingDataModel
{
    protected $horseName;
    protected $raceId;
    protected $horseId;
    protected $rating;
    protected $rank;
    protected $horseFixedOdds;

    public function __construct(int $raceId, int $horseId, float $rating, float $rank, string $horseFixedOdds, string $horseName)
    {
        $this->raceId = $raceId;
        $this->horseId = $horseId;
        $this->rating = $rating;
        $this->rank = $rank;
        $this->horseFixedOdds = $horseFixedOdds;
        $this->horseName = $horseName;
    }

    /**
     * @return string
     */
    public function getHorseName(): string
    {
        return $this->horseName;
    }

    /**
     * @return int
     */
    public function getRaceId(): int
    {
        return $this->raceId;
    }

    /**
     * @return int
     */
    public function getHorseId(): int
    {
        return $this->horseId;
    }

    /**
     * @return float
     */
    public function getRating(): float
    {
        return $this->rating;
    }

    /**
     * @return float
     */
    public function getRank(): float
    {
        return $this->rank;
    }

    /**
     * @return string
     */
    public function getHorseFixedOdds(): string
    {
        return $this->horseFixedOdds;
    }

}