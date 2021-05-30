<?php

namespace App\Helper;

use App\Enum\Algorithm\DefaultAlgorithmMethodsEnum;

class DefaultAlgorithmMethods
{
    public static function transcribe(string $algorithmMethod): string
    {
        $transcribeArray = [
            DefaultAlgorithmMethodsEnum::REFRESH_RANK()->__toString() => 'Updates RANK entries for all records stored in `tbl_hist_results`',
            DefaultAlgorithmMethodsEnum::REFRESH_RATING()->__toString() => 'Updates RATING entries for all records stored in `tbl_hist_results`',
        ];

        return $transcribeArray[$algorithmMethod];
    }
}