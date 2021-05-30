<?php

namespace App\Service\Homepage;

use App\DataTransformer\HorseDataModelTransformer;
use App\Helper\OddsHelper;
use App\Helper\ProfitLossCalculationHelper;
use App\Model\App\HorseDataModel;
use App\Model\App\HorseRatingDataModel;
use App\Model\App\Result\HorseResultDataModel;
use App\Model\App\Result\HorseResultDataSet;
use App\Model\RatingFieldResultSet;
use App\Service\App\HorseService;
use App\Service\App\ResultService;
use App\Service\DBConnector;
use Symfony\Component\HttpFoundation\Request;

class RatingFieldResultSetService
{
    /**
     * @var DBConnector
     */
    protected $dbConnector;

    /**
     * @var HorseService
     */
    protected $horseService;

    /**
     * @var ResultService
     */
    protected $resultService;

    public function __construct(DBConnector $dbConnector, HorseService $horseService, ResultService $resultService)
    {
        $this->dbConnector = $dbConnector;
        $this->horseService = $horseService;
        $this->resultService = $resultService;
    }

    /**
     * @param Request $request
     * @param bool $oddsEnabled
     * @param int|null $limit
     * @param int|null $offset
     * @return RatingFieldResultSet
     */
    public function generateRatingFieldResultSet(Request $request, bool $oddsEnabled, int $limit = null, int $offset = null): RatingFieldResultSet
    {
        $horseRatingData = $this->generateHorseRatingData($oddsEnabled);
        $horseResultData = $this->generateHorseResultData();
        $horseDataModelSet = $this->generateHorseDataModelSet($horseRatingData, $horseResultData);

        $mode = $request->query->get('mode');
        if ($mode == null || $mode == "null") {
            $mode = 2;
        }
        return $this->generateRatingFieldResults($horseDataModelSet, $mode);
    }

    public function generateHorseRatingData(bool $oddsEnabled): array
    {
        $horseRatingData = [];

        $testSql = "SELECT hr.horse_id, race_id, AVG(rating) as rating, (SUM(hr.rank)/COUNT(hr.race_id)) AS ranks, horse_fixed_odds FROM tbl_hist_results hr INNER JOIN tbl_horses h ON hr.horse_id = h.horse_id WHERE 1 GROUP BY horse_name, horse_fixed_odds";
        $result = $this->dbConnector->getDbConnection()->query($testSql);
        if ($result->num_rows > 0) {
            while ($horseData = $result->fetch_object()) {
                if (OddsHelper::oddsFilter($horseData->horse_fixed_odds, $oddsEnabled)) {
                    $horseRatingData[$horseData->horse_id][$horseData->race_id] = [
                        'rating' => $horseData->rating,
                        'rank' => $horseData->ranks,
                        'horse_fixed_odds' => $horseData->horse_fixed_odds
                    ];
                }
            }
        }

        return $horseRatingData;
    }

    protected function generateHorseResultData(): HorseResultDataSet
    {
        return (new HorseResultDataSet())->setElements($this->resultService->getHorseResultDataModels());
    }

    protected function generateHorseDataModelSet($horseRatingData, HorseResultDataSet $horseResultData): array
    {
        $horse_data = [];
        $horses = $this->horseService->getAll();
        foreach ($horses as $horse) {
            if ($horseResultData->existsElementWithHorseId($horse->getHorseId())) {
                $resultsSet = $horseResultData->getElementsWithHorseId($horse->getHorseId());
                foreach ($resultsSet as $horseResultDataModel) {
                    $race_id = $horseResultDataModel->getRaceId();
                    $position = $horseResultDataModel->getPosition();
                    $rating = '';
                    $rank = '';
                    $odds = '';

                    if (isset($horseRatingData[$horse->getHorseId()][$race_id])) {
                        $horseRatingSet = $horseRatingData[$horse->getHorseId()][$race_id];
                        $rating = $horseRatingSet["rating"];
                        $rank = $horseRatingSet["rank"];
                        $odds = $horseRatingSet['horse_fixed_odds'];
                    }

                    $horseData = new HorseDataModel(
                        $race_id,
                        $horse->getHorseId(),
                        $horse->getHorseName(),
                        $rating,
                        $rank,
                        $position,
                        $odds
                    );
                    $horse_data[] = $horseData;
                }
            }
        }

        return $horse_data;
    }

    /**
     * @param array $horseDataModelSet
     * @param int|null $mode
     * @return RatingFieldResultSet
     */
    protected function generateRatingFieldResults(array $horseDataModelSet, ?int $mode): RatingFieldResultSet
    {
        $ratingFieldResultSet = new RatingFieldResultSet();
        $currentFieldAbsoluteTotal = 0;

        $sql_raceid = "SELECT race_id FROM tbl_races";
        $result_raceid = $this->dbConnector->getDbConnection()->query($sql_raceid);

        $ratingFieldResultsArray = [];
        if ($result_raceid->num_rows > 0) {

            // output data of each row
            while ($row_id = $result_raceid->fetch_assoc()) {
                $temp_array = array();
                $race_data = (new HorseDataModelTransformer($horseDataModelSet))->transform();

                foreach ($race_data as $k => $r) {
                    if ($row_id['race_id'] == $r) {
                        $temp_array[] = $horseDataModelSet[$k];
                    }
                }
                /** @var $a HorseDataModel */
                usort($temp_array, function ($a, $b) {
                    if ($a->getRating() === $b->getRating())
                        return 0;
                    return ($a->getRating() > $b->getRating()) ? -1 : 1;
                });

                if (count($temp_array) > 0) {
                    try {
                        switch ($mode) {
                            case 1:
                                $real_result = array(
                                    $temp_array[0]
                                );
                                break;
                            case 3:
                                $real_result = array(
                                    $temp_array[0],
                                    $temp_array[1],
                                    $temp_array[2]
                                );
                                break;
                            case 2:
                            default:
                                $real_result = array(
                                    $temp_array[0],
                                    $temp_array[1]
                                );
                                break;
                        }

                        $race = $this->dbConnector->getRaceDetails($row_id['race_id']);
                        $meeting = $this->dbConnector->getMeetingDetails($race->getMeetingId());

                        if (count($real_result) > 0) {
                            /** @var HorseDataModel $horseModelData */
                            foreach ($real_result as $horseModelData) {
                                $profit = ProfitLossCalculationHelper::profitOrLossCalculation($horseModelData->getRating(), $horseModelData->getRating(), $horseModelData->getRating(), $horseModelData->getOdds(true), $horseModelData->getPosition(), $horseModelData->getHorseName());
                                $currentFieldAbsoluteTotal += $profit;
                                $ratingFieldResultSet->calculateAbsoluteTotal($profit);

                                $ratingFieldResultsArray[] = [
                                    'horseId' => $horseModelData->getHorseId(),
                                    'raceId' => $race->getRaceId(),
                                    'race' => $race,
                                    'meeting' => $meeting,
                                    'horse' => $horseModelData->getHorseName(),
                                    'rating' => number_format($horseModelData->getRating(true), 2),
                                    'rank' => number_format($horseModelData->getRank(), 2),
                                    'revenue' => $profit,
                                    'total' => $currentFieldAbsoluteTotal
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        // todo it fails
                    }
                }
            }
        }
        $ratingFieldResultSet->setResults($ratingFieldResultsArray);

        return $ratingFieldResultSet;
    }
}