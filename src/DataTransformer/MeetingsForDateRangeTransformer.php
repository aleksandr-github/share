<?php

namespace App\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;

class MeetingsForDateRangeTransformer
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function transform(): ArrayCollection
    {
        $transformArray = new ArrayCollection();

        foreach ($this->data as $meetingDayArray) {
            foreach ($meetingDayArray as $item) {
                $transformArray->add($item);
            }
        }

        return $transformArray;
    }
}