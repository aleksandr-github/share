<?php

namespace App\DataTransformer;

class HorseDataModelTransformer
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function transform()
    {
        $arrayOfRaceIds = [];
        foreach ($this->data as $item) {
            $arrayOfRaceIds[] = $item->getRaceId();
        }

        return $arrayOfRaceIds;
    }
}