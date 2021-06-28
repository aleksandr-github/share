<?php

namespace App\Service\Debug;

use App\Task\GenerateHandicapForHistoricResultTask;
use App\Task\UpdateRatingForRaceTask;

class RatingDebugService
{
    public function debugLogParse(string $searchString, $debugLog): array
    {
        $pattern = preg_quote($searchString, '/');
        $pattern = "/^.*$pattern.*\$/m";
        preg_match_all($pattern, $debugLog, $data);

        return $data;
    }

    /**
     * @param array $generateHandicapRawData
     * @param array $generateRankRawData
     * @param array $generateAVGSectionalRawData
     * @param array $getH2HPointRawData
     * @return array
     */
    public function generateSteps(
        array $generateHandicapRawData,
        array $generateRankRawData,
        array $generateAVGSectionalRawData,
        array $getH2HPointRawData,
        int $entryNumber
    ): array
    {
        $steps = [];
        $sumArray = [];

        // initial step:
        $index = $this->arraySearchPartial($generateHandicapRawData, 'AlgorithmStrategyInterface::generateHandicap()', $entryNumber);
        if ($index !== null) {
            $stepVariables = $this->getVariablesFromDebugLog($generateHandicapRawData[$index]);

            // helper section
            $horsePosition = explode('/', $stepVariables->HORSE_POSITION);
            $position = intval($horsePosition[0]);
            $remainder = $this->getRemainingDistance($stepVariables->RACE_DISTANCE_DETAILS_LENGTH);

            $subFormulaConditionPosition = "";
            $roundingFunctionUsed = "";
            if ($position == 1) {
                $subFormulaConditionPosition = "Position = 1";
                if ($stepVariables->RACE_DISTANCE_DETAILS_LENGTH < $stepVariables->ROUNDED_DISTANCE) {
                    $roundingFunctionUsed = "roundWinUp";
                    $newtime = $this->roundWinUp($stepVariables->RACE_TIME, $remainder, $stepVariables->TIMER);
                } else {
                    $roundingFunctionUsed = "roundWinDown";
                    $newtime = $this->roundWinDown($stepVariables->RACE_TIME, $remainder, $stepVariables->TIMER);
                }
            } else {
                $subFormulaConditionPosition = "Position > 1";
                if ($stepVariables->RACE_DISTANCE_DETAILS_LENGTH < $stepVariables->ROUNDED_DISTANCE) {
                    $roundingFunctionUsed = "roundLoseUp";
                    $newtime = $this->roundLoseUp(
                        $stepVariables->RACE_TIME,
                        $stepVariables->LENGTH,
                        $stepVariables->MODIFIER,
                        $remainder,
                        $stepVariables->TIMER
                    );
                } else {
                    if ($stepVariables->RACE_DISTANCE_DETAILS_LENGTH > $stepVariables->ROUNDED_DISTANCE) {
                        $roundingFunctionUsed = "roundLoseDown";
                        $newtime = $this->roundLoseDown(
                            $stepVariables->RACE_TIME,
                            $stepVariables->LENGTH,
                            $stepVariables->MODIFIER,
                            $remainder,
                            $stepVariables->TIMER
                        );
                    } else {
                        $roundingFunctionUsed = 'legacy';
                        $newtime = $stepVariables->RACE_TIME + ($stepVariables->LENGTH * $stepVariables->MODIFIER);
                    }
                }
            }

            $subFormulas = [
                'roundWinUp' => '$time + ($timer * $remainder)',
                'roundWinDown' => '$time - ($timer * $remainder)',
                'roundLoseUp' => '$time + ($length * $modifier) + ($timer * $remainder)',
                'roundLoseDown' => '$time + ($length * $modifier) - ($timer * $remainder)',
                'legacy' => '$time + ($length * $modifier)'
            ];

            $variables = [
                'raceDistance' => $stepVariables->RACE_DISTANCE_DETAILS_LENGTH,
                '$time' => $stepVariables->RACE_TIME,
                '$timer' => $stepVariables->TIMER,
                '$remainder' => $remainder,
                '$modifier' => $stepVariables->MODIFIER,
                '$length' => $stepVariables->LENGTH
            ];

            $calculation = str_replace(
                '$modifier', $variables['$modifier'],
                str_replace(
                '$length', $variables['$length'],
                str_replace(
                '$remainder', $variables['$remainder'],
                str_replace(
                '$time', $variables['$time'],
                str_replace(
                '$timer', $variables['$timer'], $subFormulas[$roundingFunctionUsed]
            )))));

            $steps['f(`handicap`)'] = [
                'result' => $stepVariables->HANDICAP_RESULT,
                'formulaResult' => $newtime,
                'formula' => $subFormulas[$roundingFunctionUsed],
                'calculation' => $calculation . ' = ' . $stepVariables->HANDICAP_RESULT,
                'subCalculations' => [
                    'roundWinUp' => $this->roundWinUp($stepVariables->RACE_TIME, $remainder, $stepVariables->TIMER),
                    'roundWinDown' => $this->roundWinDown($stepVariables->RACE_TIME, $remainder, $stepVariables->TIMER),
                    'roundLoseUp' => $this->roundLoseUp($stepVariables->RACE_TIME, $stepVariables->LENGTH, $stepVariables->MODIFIER, $remainder, $stepVariables->TIMER),
                    'roundLoseDown' => $this->roundLoseDown($stepVariables->RACE_TIME, $stepVariables->LENGTH, $stepVariables->MODIFIER, $remainder, $stepVariables->TIMER),
                    'legacy' => $stepVariables->RACE_TIME + ($stepVariables->LENGTH * $stepVariables->MODIFIER)
                ],
                'subFormulas' => $subFormulas,
                'variables' => $variables,
                'conditions' => [
                    'conditionPositionUsed' => $subFormulaConditionPosition,
                    'roundingFunctionUsed' => $roundingFunctionUsed
                ],
                'emitter' => GenerateHandicapForHistoricResultTask::class
            ];
        }

        // first step: UpdateHandicapForRaceTask
        $index = $this->arraySearchPartial($generateRankRawData, 'AlgorithmStrategyInterface::generateRank()', $entryNumber);
        if ($index !== null) {
            $stepVariables = $this->getVariablesFromDebugLog($generateRankRawData[$index]);

            // helper section
            $arrayOfHandicap = explode("@", $stepVariables->ARRAY_OF_HANDICAP);
            $nameArrayOfHandicap = explode("@", $stepVariables->NAMEARRAY_OF_HANDICAP);
            array_multisort($arrayOfHandicap, $nameArrayOfHandicap);
            //array_unshift($arrayOfHandicap, $stepVariables->MIN_HANDICAP + 1);
            $keys = array_keys($arrayOfHandicap, $stepVariables->MIN_HANDICAP);
            $sumArray = array_map(function ($x, $y) { return $x.'   '.$y; }, $arrayOfHandicap, $nameArrayOfHandicap);

            $ratingTempLine = sprintf(
                '%s / %s / 2 = %s',
                array_sum($keys),
                count($keys),
                $stepVariables->RANK
            );
            $steps['f(`rank`)'] = [
                'result' => $stepVariables->RANK,
                'formula' => 'array_sum(array_keys(ARRAY_OF_HANDICAP, MIN(handicap))) / count(array_keys(ARRAY_OF_HANDICAP, MIN(handicap)) = rank',
                'calculation' => $ratingTempLine,
                'subCalculations' => (object)[
                    'ARRAY_OF_HANDICAP' => (object)$sumArray,
                    'array_keys(ARRAY_OF_HANDICAP, MIN(handicap))' => (object)$keys,
                    'array_sum(@arrayKeys)' => array_sum($keys),
                    'count(@arrayKeys)' => count($keys),
                    'MIN(handicap)' => $stepVariables->MIN_HANDICAP
                ],
                'subFormulas' => (object)[
                    'ARRAY_OF_HANDICAP' => '@see UpdateHandicapForRaceTask::getArrayOfHandicap()',
                    'MIN(handicap)' => '@see UpdateHandicapForRaceTask::updateRankSectionForRace()::$handicapResults'
                ],
                'emitter' => UpdateRatingForRaceTask::class
            ];
        }

        // second step: UpdateHandicapForRaceTask
        $index = $this->arraySearchPartial($generateAVGSectionalRawData, 'AlgorithmStrategyInterface::generateAVGSectional()', $entryNumber);
        if ($index !== null) {
            $stepVariables = $this->getVariablesFromDebugLog($generateAVGSectionalRawData[$index]);

            // helper section
            $sectionalArray = explode("@", $stepVariables->AVGSECTIONALARRAY);
            rsort($sectionalArray);
            array_unshift($sectionalArray, $stepVariables->MAXSECAVG + 1);
            $keys = array_keys($sectionalArray, $stepVariables->MAXSECAVG);

            $ratingTempLine = sprintf(
                '%s / %s / 2 = %s',
                array_sum($keys),
                count($keys),
                $stepVariables->AVGSECTIONAL
            );
            $steps['f(`avgsectional`)'] = [
                'result' => $stepVariables->AVGSECTIONAL,
                'formula' => '(array_sum(array_keys(AVG_SECTIONAL_ARRAY, MAX(avgsec))) / count(array_keys(, AVG_SECTIONAL_ARRAY, MAX(avgsec))) / 2 = avgsectional',
                'calculation' => $ratingTempLine,
                'subCalculations' => (object)[
                    'AVG_SECTIONAL_ARRAY' => (object)$sectionalArray,
                    'array_keys(AVG_SECTIONAL_ARRAY, MAX(avgsec)' => (object)$keys,
                    'array_sum(@arrayKeys)' => array_sum($keys),
                    'count(@arrayKeys)' => count($keys),
                ],
                'subFormulas' => (object)[
                    'AVG_SECTIONAL_ARRAY' => '@see UpdateHandicapForRaceTask::getArrayOfAVGSectional()',
                    'MAX(avgsec)' => '@see UpdateHandicapForRaceTask::updateSectionalAVGForRace()::$handicapResults'
                ],
                'emitter' => UpdateRatingForRaceTask::class
            ];
        }

        // third step UpdateHandicapForRaceTask
        $index = $this->arraySearchPartial($getH2HPointRawData, 'AlgorithmStrategyInterface::getH2HPoint()', $entryNumber);
        if ($index !== null) {
            $stepVariables = $this->getVariablesFromDebugLog($getH2HPointRawData[$index]);

            $ratingTempLine = sprintf(
                "%s + %s = %s",
                $stepVariables->AVGSECTIONAL,
                $stepVariables->ROWRANK,
                $stepVariables->RATEPOS
            );
            $steps['f(`ratePos`)'] = [
                'result' => $stepVariables->RATEPOS,
                'formula' => 'avgsectional + rank = ratePos',
                'calculation' => $ratingTempLine,
                'emitter' => UpdateRatingForRaceTask::class
            ];

            $ratingTempLine = sprintf(
                "%s / 2 = %s",
                $stepVariables->H2HHORSE,
                $stepVariables->H2HPOINT
            );
            $steps['f(`h2hpoint`)'] = [
                'result' => $stepVariables->H2HPOINT,
                'formula' => 'horse_h2h / 2 = h2hpoint',
                'calculation' => $ratingTempLine,
                'emitter' => UpdateRatingForRaceTask::class
            ];

            $ratingTempLine = sprintf(
                "%s + %s = %s",
                $stepVariables->RATEPOS,
                $stepVariables->H2HPOINT,
                $stepVariables->RATING
            );
            $steps['f(`rating`)'] = [
                'result' => $stepVariables->RATING,
                'formula' => 'ratePos + h2hpoint = rating',
                'calculation' => $ratingTempLine,
                'emitter' => UpdateRatingForRaceTask::class
            ];
        }

        return $steps;
    }



    /**
     * @param array $generateHandicapRawData
     * @param array $generateRankRawData
     * @param array $generateAVGSectionalRawData
     * @param array $getH2HPointRawData
     * @return array
     */
    public function generateAvgRankSteps(
        array $generateHandicapRawData,
        array $generateRankRawData,
        array $generateAVGSectionalRawData,
        array $getH2HPointRawData,
        int $entryNumber
    ): array
    {
        $steps = [];
        $infoArray = [];

        // first step: UpdateHandicapForRaceTask
        $index = $this->arraySearchPartial($generateRankRawData, 'AlgorithmStrategyInterface::generateRank()', $entryNumber);
        if ($index !== null) {
            $stepVariables = $this->getVariablesFromDebugLog($generateRankRawData[$index]);

            // helper section
            $rankArray = explode("&", $stepVariables->CALCULATION_OF_AVERAGE_RANK);

            $n = count($rankArray);

            for($i=1;$i<$n;$i++) {
                $distanceArray = explode("@", $rankArray[$i]);
                $m = count($distanceArray);
                for($j=1;$j<$m;$j++) {
                    $detailArray = explode("#", $distanceArray[$j]);
                    $dd = $detailArray[2];
                    $ee = $detailArray[3]."  ".$detailArray[3]."  ".$detailArray[4];
                    $infoArray[] = $ee;
                }
                $steps[$i] = [
                    'RANK' => (object)[
                        $dd => (object)$infoArray
                    ],
                ];
            }

        }

        return $steps;
    }

    private function arraySearchPartial(array $arr, string $keyword, $entryNumber)
    {
        foreach($arr as $index => $string) {
            if ($index == $entryNumber)
                if (strpos($string, $keyword) !== FALSE)
                    return $index;
        }

        return null;
    }

    // ugly as fuck
    private function getVariablesFromDebugLog(string $debugLine): object
    {
        $variablesArray = [];

        $arrayString = explode(";", $debugLine);
        foreach ($arrayString as $key => $item) {
            if ($key != 0) {
                $keyValue = explode("=", $item);
                if (count($keyValue) === 2) {
                    $variablesArray[$keyValue[0]] = $keyValue[1];
                }
            }
        }

        return (object)$variablesArray;
    }

    public function generateHumanReadableOutput()
    {
        return "todo";
    }

    #region helpers
    protected function getRemainingDistance($distance): int
    {
        if ($distance % 10 < 5) {
            $distance -= $distance % 10;
        } else {
            $distance += (10 - ($distance % 10));
        }

        if ($distance % 100 < 50) {
            $reminder_distance = $distance % 100;
        } else {
            $reminder_distance = (100 - ($distance % 100));
        }

        return $reminder_distance;
    }

    protected function roundWinUp($time, $remainder, $timer)
    {
        return $time + ($timer * $remainder);
    }

    protected function roundWinDown($time, $remainder, $timer)
    {
        return $time - ($timer * $remainder);
    }

    protected function roundLoseUp($time, $length, $modifier, $remainder, $timer)
    {
        return $time + ($length * $modifier) + ($timer * $remainder);
    }

    protected function roundLoseDown($time, $length, $modifier, $remainder, $timer)
    {
        return $time + ($length * $modifier) - ($timer * $remainder);
    }
    #endregion
}