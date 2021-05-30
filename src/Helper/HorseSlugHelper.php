<?php

namespace App\Helper;

class HorseSlugHelper
{
    public static function generate(string $horseFullName): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', strtolower($horseFullName));
    }
}