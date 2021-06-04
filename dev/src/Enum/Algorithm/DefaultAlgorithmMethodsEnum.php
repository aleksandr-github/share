<?php

namespace App\Enum\Algorithm;

use MyCLabs\Enum\Enum;

/**
 * @method static REFRESH_RANK()
 * @method static REFRESH_RATING()
 */
class DefaultAlgorithmMethodsEnum extends Enum
{
    private const REFRESH_RANK = 'Rank';
    private const REFRESH_RATING = 'Rating';
}