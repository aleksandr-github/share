<?php

namespace App\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;

class RacesForMeetingsTransformer
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function transform(): ArrayCollection
    {
        $transformArray = new ArrayCollection();

        foreach ($this->data as $raceItem) {
            foreach ($raceItem as $item) {
                $transformArray->add($item);
            }
        }

        return $transformArray;
    }
}