<?php

namespace App\Helper;

class ResultsDataHelper
{
    /**
     * @param object $result
     * @param $results
     * @return bool
     */
    public static function isResultBetter(object $result, $results): bool
    {
        if ($result->position == $results['position']) {
            return false;
        } elseif ($result->position < $results['position']) {
            return true;
        }

        return false;
    }
}