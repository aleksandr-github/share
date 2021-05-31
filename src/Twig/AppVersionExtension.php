<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppVersionExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('appVersion', [$this, 'appVersion']),
        ];
    }

    public function appVersion(): string
    {
        return $_ENV['appVersion'] . '.' . exec('git log --format="%h" -n 1');
    }
}