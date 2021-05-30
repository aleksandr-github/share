<?php

namespace App\Model\App\Result;

class HorseResultDataModel
{
    protected $raceId;
    protected $horseId;
    protected $position;

    public function __construct(int $raceId, int $horseId, int $position)
    {
        $this->raceId = $raceId;
        $this->horseId = $horseId;
        $this->position = $position;
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
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }


}