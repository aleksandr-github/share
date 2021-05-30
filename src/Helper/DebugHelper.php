<?php

namespace App\Helper;

class DebugHelper
{
    public static function debug($data)
    {
        if ($_ENV['app_env'] == "DEV") {
            dump($data);
        }
    }
}