<?php

namespace App\Model\App;

class HorseDataModel
{
    protected $raceId;
    protected $horseId;
    protected $horseName;
    protected $rating;
    protected $rank;
    protected $position;
    protected $odds;

    public function __construct(
        $race_id,
        $horseId,
        $horseName,
        $rating,
        $rank,
        $position,
        $odds
    ) {
        $this->raceId = $race_id;
        $this->horseId = $horseId;
        $this->horseName = $horseName;
        $this->rating = $rating;
        $this->rank = $rank;
        $this->position = $position;
        $this->odds = $odds;
    }

    /**
     * @return mixed
     */
    public function getRaceId()
    {
        return $this->raceId;
    }

    /**
     * @param mixed $raceId
     * @return HorseDataModel
     */
    public function setRaceId($raceId)
    {
        $this->raceId = $raceId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHorseId()
    {
        return $this->horseId;
    }

    /**
     * @param mixed $horseId
     * @return HorseDataModel
     */
    public function setHorseId($horseId)
    {
        $this->horseId = $horseId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHorseName()
    {
        return $this->horseName;
    }

    /**
     * @param mixed $horseName
     * @return HorseDataModel
     */
    public function setHorseName($horseName)
    {
        $this->horseName = $horseName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRating()
    {
        return $this->rating;
    }

    /**
     * @param mixed $rating
     * @return HorseDataModel
     */
    public function setRating($rating)
    {
        $this->rating = $rating;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * @param mixed $rank
     * @return HorseDataModel
     */
    public function setRank($rank)
    {
        $this->rank = $rank;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPosition(bool $asInteger = true)
    {
        if ($asInteger) {
            return intval($this->position);
        }

        return $this->position;
    }

    /**
     * @param mixed $position
     * @return HorseDataModel
     */
    public function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOdds(bool $asFloat = false)
    {
        if ($asFloat) {
            return floatval(str_replace("$", "", $this->odds));
        }

        return $this->odds;
    }

    /**
     * @param mixed $odds
     * @return HorseDataModel
     */
    public function setOdds($odds)
    {
        $this->odds = $odds;
        return $this;
    }
}