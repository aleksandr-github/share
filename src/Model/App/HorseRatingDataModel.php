<?php

namespace App\Model\App;

class HorseRatingDataModel
{
    protected $horseName;
    protected $rating;
    protected $rank;
    protected $horseFixedOdds;

    public function __construct($horseRatingData)
    {
        dump($horseRatingData);
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
     * @return HorseRatingDataModel
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
     * @return HorseRatingDataModel
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
     * @return HorseRatingDataModel
     */
    public function setRank($rank)
    {
        $this->rank = $rank;
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
     * @return HorseRatingDataModel
     */
    public function setHorseFixedOdds($horseFixedOdds)
    {
        $this->horseFixedOdds = $horseFixedOdds;
        return $this;
    }


}