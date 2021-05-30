<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DistanceFormatExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('distanceFormat', [$this, 'distanceFormat']),
        ];
    }

    public function distanceFormat($value): string
    {
        return number_format($value, 0) . 'm';
    }
}