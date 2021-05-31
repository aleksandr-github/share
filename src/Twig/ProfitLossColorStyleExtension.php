<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProfitLossColorStyleExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('profitLossColorStyle', [$this, 'profitLossColorStyle']),
        ];
    }

    public function profitLossColorStyle($value): string
    {
        if ($value > 0) {
            return 'alert-success';
        }

        return 'alert-danger';
    }
}