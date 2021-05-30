<?php

namespace App\Helper;

class ArrayObjectHelper
{
    // Without static typing, you can also pass an array and nothing will happen
    // it's because DataModels (as of 14.04.2021) are still in development
    public static function convertToArray($object): array
    {
        return (array)$object;
    }

    // Without static typing, you can also pass an object and nothing will happen
    // it's because DataModels (as of 14.04.2021) are still in development
    public static function covertToObject($array): object
    {
        return (object)$array;
    }
}