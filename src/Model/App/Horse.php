<?php

namespace App\Model\App;

use JsonSerializable;

class Horse implements JsonSerializable
{
    protected $horseId;
    protected $horseName;
    protected $horseSlug;
    protected $horseLatestResults;
    protected $addedOn;

    public function __construct($horseData)
    {
        $this->horseId = $horseData->horse_id;
        $this->horseName = $horseData->horse_name;
        $this->horseSlug = $horseData->horse_slug;
        $this->horseLatestResults = $horseData->horse_latest_results;
        $this->addedOn = $horseData->added_on;
    }

    /**
     * @return mixed
     */
    public function getHorseId()
    {
        return $this->horseId;
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
     * @return Horse
     */
    public function setHorseName($horseName)
    {
        $this->horseName = $horseName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHorseSlug()
    {
        return $this->horseSlug;
    }

    /**
     * @param mixed $horseSlug
     * @return Horse
     */
    public function setHorseSlug($horseSlug)
    {
        $this->horseSlug = $horseSlug;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHorseLatestResults()
    {
        return $this->horseLatestResults;
    }

    /**
     * @param mixed $horseLatestResults
     * @return Horse
     */
    public function setHorseLatestResults($horseLatestResults)
    {
        $this->horseLatestResults = $horseLatestResults;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAddedOn()
    {
        return $this->addedOn;
    }

    /**
     * @param mixed $addedOn
     * @return Horse
     */
    public function setAddedOn($addedOn)
    {
        $this->addedOn = $addedOn;
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