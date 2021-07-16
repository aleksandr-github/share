<?php

namespace App\Helper;

use App\Model\App\HorseDataModel;

final class ProfitLossCalculationHelper
{
    public static function profitOrLossCalculation($max_1, $max_2, $max_3,  $rating, $odds, $position, string $horseName = null)
    {
        if ($odds == "Bet" || $odds == "0" || $odds == 0) {
            return 0;
        }

        $profitOrLoss = 0;

        if (!empty($position)) {
            if ($rating && $position > 2) {
                if ($rating > 0) {
                    if ($rating == $max_1 || $rating == $max_2 || $rating == $max_3) {
                        $profitOrLoss = 10 * 0 - 10;
                    } else {
                        $profitOrLoss = 0;
                    }
                }
            } else {
                if ($rating > 0) {
                    if ($rating == $max_1 || $rating == $max_2 || $rating == $max_3) {
                        //  $pos =  explode('/', $resavg->horse_position);
                        //  $position =  intval($pos[0]);

                        if ($position != 1) {
                            $profitOrLoss = 10 * 0 - 10;
                        } else {
                            $profitOrLoss = 10 * $odds - 10;
                        }
                    } else {
                        $profitOrLoss = 0;
                    }
                }
            }
        } else {
            $profitOrLoss = 0;
        }

        return floatval($profitOrLoss);
    }

    /**
     * @param \App\Model\App\HorseDataModel $horseDataModel
     * @param bool $treatAsAWinner
     * @return float|int
     */
    public static function simpleProfitCalculation(HorseDataModel $horseDataModel, bool $treatAsAWinner = false)
    {
        $odds = $horseDataModel->getOdds();
        if ($odds == "Bet" || $odds == "0" || $odds === 0) {
            return 0;
        }

        if ($treatAsAWinner) {
            /**
             * <pun>
             *  <Rocky Movie Music>
             *      YOU SHOULD ALWAYS THINK YOU'RE THE WINNER!
             *  </Rocky Movie Music>
             * </pun>
             * )
             * 🎵🎵 https://www.youtube.com/watch?v=btPJPFnesV4 🎵🎵
             *
             * \m/_(>_<)_\m/
             *
             * (yeah, git-blame me)
             */
            $position = 1;
        } else {
            $position = $horseDataModel->getPosition();
        }

        return $position == "" ? 0 : (($position == 1) ? ((10 * $horseDataModel->getOdds(true)) - 10) : -10);
    }
}