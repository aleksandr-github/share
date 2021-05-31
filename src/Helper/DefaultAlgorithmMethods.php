<?php

namespace App\Helper;

use App\Enum\Algorithm\DefaultAlgorithmMethodsEnum;

class DefaultAlgorithmMethods
{
    public static function transcribe(string $algorithmMethod): string
    {
        $transcribeArray = [
            DefaultAlgorithmMethodsEnum::RESET_HANDICAP()->__toString() => 'Reset HANDICAP value on all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::RESET_SECTIONAL()->__toString() => 'Reset SECTIONAL value on all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::RESET_RANK()->__toString() => 'Reset RANK value on all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::RESET_RATING()->__toString() => 'Reset SECTIONAL value on all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::RESET_ALL()->__toString() => 'Performs all of the above functions on all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::UPDATE_SECTIONAL()->__toString() => 'Updates all SECTIONAL entries on all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::UPDATE_HANDICAP()->__toString() => 'Updates all HANDICAP entries on all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::UPDATE_RANK()->__toString() => 'Updates all RANK entries on all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::UPDATE_RATING()->__toString() => 'Updates every rating field on all records stored in `tbl_hist_results`',
        ];

        return $transcribeArray[$algorithmMethod];
    }
}