<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MainPageFilterStatusExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('filterStatus', [$this, 'filterStatus']),
        ];
    }

    public function filterStatus($state): string
    {
        switch ($state) {
            case "true":
                return 'btn-warning';
            default:
                return 'btn-primary';
        }
    }
}