<?php

namespace App\Model\App;

use JsonSerializable;

class HistoricResult implements JsonSerializable
{
    protected $histId;
    protected $raceId;
    protected $raceDistance;
    protected $horseId;
    protected $hNum;
    protected $horsePosition;
    protected $horseWeight;
    protected $horseFixedOdds;
    protected $horseH2H;
    protected $tempH2H;
    protected $prize;
    protected $raceTime;
    protected $length;
    protected $sectional;
    protected $avgsec;
    protected $avgsectional;
    protected $handicap;
    protected $rating;
    protected $rank;

    public function __construct(object $historicResult)
    {
        $this->histId = $historicResult->hist_id;
        $this->raceId = $historicResult->race_id;
        $this->raceDistance = $historicResult->race_distance;
        $this->horseId = $historicResult->horse_id;
        $this->hNum = $historicResult->h_num;
        $this->horsePosition = $historicResult->horse_position;
        $this->horseWeight = $historicResult->horse_weight;
        $this->horseFixedOdds = $historicResult->horse_fixed_odds;
        $this->horseH2H = $historicResult->horse_h2h;
        $this->tempH2H = $historicResult->temp_h2h;
        $this->prize = $historicResult->prize;
        $this->raceTime = $historicResult->race_time;
        $this->length = $historicResult->length;
        $this->sectional = $historicResult->sectional;
        $this->avgsec = $historicResult->avgsec;
        $this->avgsectional = $historicResult->avgsectional;
        $this->handicap = $historicResult->handicap;
        $this->rating = $historicResult->rating;
        $this->rank = $historicResult->rank;
    }

    /**
     * @return mixed
     */
    public function getHistId()
    {
        return $this->histId;
    }

    /**
     * @param mixed $histId
     * @return HistoricResult
     */
    public function setHistId($histId)
    {
        $this->histId = $histId;
        return $this;
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
     * @return HistoricResult
     */
    public function setRaceId($raceId)
    {
        $this->raceId = $raceId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRaceDistance()
    {
        return $this->raceDistance;
    }

    /**
     * @param mixed $raceDistance
     * @return HistoricResult
     */
    public function setRaceDistance($raceDistance)
    {
        $this->raceDistance = $raceDistance;
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
     * @return HistoricResult
     */
    public function setHorseId($horseId)
    {
        $this->horseId = $horseId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHNum()
    {
        return $this->hNum;
    }

    /**
     * @param mixed $hNum
     * @return HistoricResult
     */
    public function setHNum($hNum)
    {
        $this->hNum = $hNum;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHorsePosition()
    {
        return $this->horsePosition;
    }

    /**
     * @param mixed $horsePosition
     * @return HistoricResult
     */
    public function setHorsePosition($horsePosition)
    {
        $this->horsePosition = $horsePosition;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHorseWeight()
    {
        return $this->horseWeight;
    }

    /**
     * @param mixed $horseWeight
     * @return HistoricResult
     */
    public function setHorseWeight($horseWeight)
    {
        $this->horseWeight = $horseWeight;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHorseFixedOdds()
    {
        return $this->horseFixedOdds;
    }

    /**
     * @param mixed $horseFixedOdds
     * @return HistoricResult
     */
    public function setHorseFixedOdds($horseFixedOdds)
    {
        $this->horseFixedOdds = $horseFixedOdds;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHorseH2H()
    {
        return $this->horseH2H;
    }

    /**
     * @param mixed $horseH2H
     * @return HistoricResult
     */
    public function setHorseH2H($horseH2H)
    {
        $this->horseH2H = $horseH2H;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTempH2H()
    {
        return $this->tempH2H;
    }

    /**
     * @param mixed $tempH2H
     * @return HistoricResult
     */
    public function setTempH2H($tempH2H)
    {
        $this->tempH2H = $tempH2H;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPrize()
    {
        return $this->prize;
    }

    /**
     * @param mixed $prize
     * @return HistoricResult
     */
    public function setPrize($prize)
    {
        $this->prize = $prize;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRaceTime()
    {
        return $this->raceTime;
    }

    /**
     * @param mixed $raceTime
     * @return HistoricResult
     */
    public function setRaceTime($raceTime)
    {
        $this->raceTime = $raceTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @param mixed $length
     * @return HistoricResult
     */
    public function setLength($length)
    {
        $this->length = $length;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSectional()
    {
        return $this->sectional;
    }

    /**
     * @param mixed $sectional
     * @return HistoricResult
     */
    public function setSectional($sectional)
    {
        $this->sectional = $sectional;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAvgsec()
    {
        return $this->avgsec;
    }

    /**
     * @param mixed $avgsec
     * @return HistoricResult
     */
    public function setAvgsec($avgsec)
    {
        $this->avgsec = $avgsec;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAvgsectional()
    {
        return $this->avgsectional;
    }

    /**
     * @param mixed $avgsectional
     * @return HistoricResult
     */
    public function setAvgsectional($avgsectional)
    {
        $this->avgsectional = $avgsectional;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHandicap()
    {
        return $this->handicap;
    }

    /**
     * @param mixed $handicap
     * @return HistoricResult
     */
    public function setHandicap($handicap)
    {
        $this->handicap = $handicap;
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
     * @return HistoricResult
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
     * @return HistoricResult
     */
    public function setRank($rank)
    {
        $this->rank = $rank;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $jsonSerializedArray = [];
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties() as $property) {
            $jsonSerializedArray[$property->name] = $this->{$property->name};
        }

        return $jsonSerializedArray;
    }
}
