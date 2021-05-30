<?php

namespace App\Helper;

use App\Model\DateRange;
use DateTime;

class DateRangeBuilder
{
    public static function create($start, $end, $format = 'Y-m-d'): DateRange
    {
        $dateRange = new DateRange();
        $start = new DateTime($start);
        $end = new DateTime($end);
        $invert = $start > $end;
        $dates = array();
        $dates[] = $start->format($format);
        while ($start != $end) {
            $start->modify(($invert ? '-' : '+') . '1 day');
            $dates[] = $start->format($format);
        }

        foreach ($dates as $dateKey => $date) {
            $dateRange->add($date);
        }

        return $dateRange;
    }
}