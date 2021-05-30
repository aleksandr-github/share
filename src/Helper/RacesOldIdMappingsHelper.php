<?php

namespace App\Helper;

class RacesOldIdMappingsHelper
{
    public static function generate(array $races)
    {
        $oldRaceIdMappingArray = [];
        foreach ($races as $race) {
            if (is_object($race)) {
                $oldRaceIdMappingArray[$race->old_race_id] = $race->race_id;
            } else {
                $oldRaceIdMappingArray[$race['old_race_id']] = $race['race_id'];
            }
        }

        return $oldRaceIdMappingArray;
    }
}