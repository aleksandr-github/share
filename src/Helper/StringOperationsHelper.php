<?php

namespace App\Helper;

class StringOperationsHelper
{
    public static function toSnakeCase(string $string)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', str_replace('/', '/ ', $string))));
    }
}