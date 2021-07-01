<?php

namespace App\Controller\Debug;

use App\Service\Algorithm\DefaultAlgorithm;
use App\Service\Algorithm\Strategy\AlgorithmStrategyInterface;
use App\Service\DBConnector;
use App\Service\Debug\RatingDebugService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RatingDebugController extends AbstractController
{
    /**
     * @var RatingDebugService
     */
    protected $debugService;
    protected $currentAlgorithm;

    public function __construct(RatingDebugService $debugService, ContainerInterface $container)
    {
        $this->debugService = $debugService;
        $this->currentAlgorithm = $container->get('algorithmContext')->getAlgorithm();
    }

    /**
     * @Route("/debug/rating/horse/{horseId}/race/{raceId}/historic_record/{histId}/entry/{entryNumber?0}", name="debug_rating")
     *
     * @param int $horseId
     * @param int $raceId
     * @param int $histId
     * @return JsonResponse
     */
    public function index(int $horseId, int $raceId, int $histId, int $entryNumber): JsonResponse
    {
        // todo move to service
        if (!$this->supports($this->currentAlgorithm)) {
            return new JsonResponse([
                'code' => 501,
                'message' => "Current algorithm not supported by " . __CLASS__,
            ]);
        }

        $start = microtime(true);
        $dbConnector = new DBConnector();
        $historicResult = $dbConnector->getHistoricResult($histId);
        $race = $dbConnector->getRaceDetails($raceId);
        $horse = $dbConnector->getHorseDetails($horseId);

        $file = APP_ROOT . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . "debug_algorithm.log";
        $contents = file_get_contents($file);

        $handicapRawData = "generateHandicap();HORSE=".$historicResult->getHorseId().";RACE=".$historicResult->getRaceId().";RACE_DISTANCE=".$historicResult->getRaceDistance().";HORSE_POSITION=".$historicResult->getHorsePosition();
        $generateHandicapRawData = $this->debugService->debugLogParse($handicapRawData, $contents);

        $rankRawData = "generateRank();HORSE=".$historicResult->getHorseId().";RACE=".$historicResult->getRaceId().";RACE_DISTANCE=".$historicResult->getRaceDistance();
        $generateRankRawData = $this->debugService->debugLogParse($rankRawData, $contents);

        $avgSectionalRawData = "generateAVGSectional();HORSE=".$historicResult->getHorseId().";RACE=".$historicResult->getRaceId().";RACE_DISTANCE=".$historicResult->getRaceDistance();
        $generateAVGSectionalRawData = $this->debugService->debugLogParse($avgSectionalRawData, $contents);

        $h2hPointRawData = "AlgorithmStrategyInterface::getH2HPoint();HORSE=".$historicResult->getHorseId().";RACE=".$historicResult->getRaceId().";RACE_DISTANCE=".$historicResult->getRaceDistance().";HISTID=".$histId;
        $getH2HPointRawData = $this->debugService->debugLogParse($h2hPointRawData, $contents);

        $end = microtime(true) - $start;
        return new JsonResponse([
            'steps' => $this->debugService->generateSteps(
                $generateHandicapRawData[0],
                $generateRankRawData[0],
                $generateAVGSectionalRawData[0],
                $getH2HPointRawData[0],
                $entryNumber
            ),
            'humanReadable' => $this->debugService->generateHumanReadableOutput(),
            'models' => (object)[
                'horse' => $horse,
                'race' => $race,
                'historicResult' => $historicResult,
            ],
            'rawData' => (object)[
                'generateHandicap' => (object)$generateHandicapRawData[0],
                'generateAVGSectionalRawData' => (object)$generateAVGSectionalRawData[0],
                'generateRank' => (object)$generateRankRawData[0],
                'h2hPoints' => (object)$getH2HPointRawData[0],
            ],
            'performance' => (float)number_format($end, 2)
        ]);
    }


    /**
     * @Route("/debug/rating/horse/{horseId}/race/{raceId}/historic_record/{histId}/avgrank/{entryNumber?0}", name="debug_avgrank")
     *
     * @param int $horseId
     * @param int $raceId
     * @param int $histId
     * @return JsonResponse
     */
    public function avgrank(int $horseId, int $raceId, int $histId, int $entryNumber): JsonResponse
    {
        // todo move to service
        if (!$this->supports($this->currentAlgorithm)) {
            return new JsonResponse([
                'code' => 501,
                'message' => "Current algorithm not supported by " . __CLASS__,
            ]);
        }

        $start = microtime(true);
        $dbConnector = new DBConnector();
        $historicResult = $dbConnector->getHistoricResult($histId);
        $race = $dbConnector->getRaceDetails($raceId);
        $horse = $dbConnector->getHorseDetails($horseId);

        $file = APP_ROOT . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . "debug_algorithm.log";
        $contents = file_get_contents($file);

        $handicapRawData = "generateHandicap();HORSE=".$historicResult->getHorseId().";RACE=".$historicResult->getRaceId().";RACE_DISTANCE=".$historicResult->getRaceDistance().";HORSE_POSITION=".$historicResult->getHorsePosition();
        $generateHandicapRawData = $this->debugService->debugLogParse($handicapRawData, $contents);

        $rankRawData = "generateRank();HORSE=".$historicResult->getHorseId().";RACE=".$historicResult->getRaceId().";RACE_DISTANCE=".$historicResult->getRaceDistance();
        $generateRankRawData = $this->debugService->debugLogParse($rankRawData, $contents);

        $avgSectionalRawData = "generateAVGSectional();HORSE=".$historicResult->getHorseId().";RACE=".$historicResult->getRaceId().";RACE_DISTANCE=".$historicResult->getRaceDistance();
        $generateAVGSectionalRawData = $this->debugService->debugLogParse($avgSectionalRawData, $contents);

        $h2hPointRawData = "AlgorithmStrategyInterface::getH2HPoint();HORSE=".$historicResult->getHorseId().";RACE=".$historicResult->getRaceId().";RACE_DISTANCE=".$historicResult->getRaceDistance().";HISTID=".$histId;
        $getH2HPointRawData = $this->debugService->debugLogParse($h2hPointRawData, $contents);

        $end = microtime(true) - $start;
        return new JsonResponse([
            'calculation' => 'sum(rank)/distance count',
            'steps' => $this->debugService->generateAvgRankSteps(
                $generateHandicapRawData[0],
                $generateRankRawData[0],
                $generateAVGSectionalRawData[0],
                $getH2HPointRawData[0],
                $entryNumber
            )
        ]);
    }


    // todo move to service and add interface
    private function supports(AlgorithmStrategyInterface $algorithm): bool
    {
        if (!in_array(get_class($algorithm), $this->supportedAlgorithms())) {
            return false;
        }

        return true;
    }

    // todo move to service and add interface
    private function supportedAlgorithms(): array
    {
        return [
            DefaultAlgorithm::class
        ];
    }

}