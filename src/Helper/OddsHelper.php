<?php

namespace App\Helper;

class OddsHelper
{
    public static function oddsFilter($odds, bool $isOddsEnabled): bool
    {
        $oddsThreshold = floatval($_ENV['oddsThreshold']);

        if ($isOddsEnabled) {
            // $3.4 as string
            $oddsFloat = floatval(str_replace("$", "", $odds));
            if ($oddsFloat <= $oddsThreshold) {
                return false;
            }
        }

        return true;
    }
}