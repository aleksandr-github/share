<?php

namespace App\Model;

class DateRange
{
    /**
     * @var array
     */
    protected $dateRanges;

    public function __construct()
    {
        $this->dateRanges = [];
    }

    public function add($date): DateRange
    {
        $this->dateRanges[] = $date;

        return $this;
    }

    /**
     * @return array|string[]
     */
    public function getAll(): array
    {
        return $this->dateRanges;
    }

    public function __toString()
    {
        return implode(" => ", $this->dateRanges);
    }

    public function toSQLQuery(): string
    {
        $str = "";

        foreach ($this->dateRanges as $date) {
            $str .= "\"".$date."\",";
        }

        return substr($str, 0, -1);
    }
}