<?php

namespace App\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;

class HorsesForRacesTransformer
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function transform(): ArrayCollection
    {
        $transformArray = new ArrayCollection();

        foreach ($this->data as $urlKey => $meetingDayArray) {
            foreach ($meetingDayArray as $meetingIDKey => $item) {
                $meetingArray = new ArrayCollection();
                foreach ($item as $horseRace) {
                    $meetingArray->add($horseRace);
                }
                $transformArray->set($meetingIDKey, $meetingArray);
            }
        }

        return $transformArray;
    }
}