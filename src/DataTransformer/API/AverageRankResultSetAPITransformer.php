<?php

namespace App\DataTransformer\API;

use App\Model\AverageRankFieldResultSet;
use NumberFormatter;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extra\Intl\IntlExtension;

/**
 * TODO This is considered WIP and yet not used in app
 * We'll use it to obtain data for datatables for performance boost
 *
 * Class AverageRankResultSetAPITransformer
 * @package App\DataTransformer\API
 */
class AverageRankResultSetAPITransformer
{
    protected $averageRankFieldResultSet;
    protected $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function transform(AverageRankFieldResultSet $averageRankFieldResultSet)
    {
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $transformedData = [];
        foreach ($averageRankFieldResultSet->getResults() as $result) {
            /** @var \App\Model\App\Race $race */
            $race = $result['race'];
            /** @var \App\Model\App\Meeting $meeting */
            $meeting = $result['meeting'];
            $pathOfRace = $this->router->generate('races_details', ['race' => $result['raceId'], 'meeting' => $result['meeting']->getMeetingId(), 'average' => 'average']);
            $pathOfHorse = $this->router->generate('horse_details', ['horse' => $result['horseId']]);
            $transformedData[] = [
                $result['raceId'],
                $race->getRaceTitle(),
                $result['horse'],
                $result['avgRank'],
                $formatter->formatCurrency($result['revenue'], 'USD'),
                $formatter->formatCurrency($result['total'], 'USD'),
                $pathOfRace,
                $meeting->getMeetingDate(),
                $race->getRaceScheduleTime(),
                number_format($race->getRaceDistance(), 0),
                $pathOfHorse,
            ];
        }

        return $transformedData;
    }
}