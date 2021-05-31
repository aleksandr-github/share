<?php

namespace App\Enum\Algorithm;

use MyCLabs\Enum\Enum;

/**
 * @method static RESET_HANDICAP()
 * @method static RESET_SECTIONAL()
 * @method static RESET_RANK()
 * @method static RESET_RATING()
 * @method static RESET_ALL()
 * @method static UPDATE_SECTIONAL()
 * @method static UPDATE_HANDICAP()
 * @method static UPDATE_RANK()
 * @method static UPDATE_RATING()
 */
class DefaultAlgorithmMethodsEnum extends Enum
{
    private const RESET_HANDICAP = 'ResetHandicapTask';
    private const RESET_SECTIONAL = 'ResetSectionalTask';
    private const RESET_RANK = 'ResetRankTask';
    private const RESET_RATING = 'ResetRatingTask';
    private const RESET_ALL = 'ResetAllTask';
    private const UPDATE_SECTIONAL = 'UpdateSectionalTask';
    private const UPDATE_HANDICAP = 'UpdateHandicapTask';
    private const UPDATE_RANK = 'UpdateRankTask';
    private const UPDATE_RATING = 'UpdateRatingTask';
}