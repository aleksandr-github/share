<?php

namespace App\Helper;

class RaceDistanceArrayHelper
{
    /**
     * @param array $races
     * @return array
     */
    public static function generateRaceDistanceArray(array $races): array
    {
        $raceDistanceArray = [];

        foreach ($races as $race) {
            if (is_object($race)) {
                $raceDistanceArray[$race->race_id] = $race->race_distance;
            } else {
                $raceDistanceArray[$race["race_id"]] = $race["race_distance"];
            }
        }

        return $raceDistanceArray;
    }
}