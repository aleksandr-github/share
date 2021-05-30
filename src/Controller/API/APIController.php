<?php

namespace App\Controller\API;

use App\DataTransformer\API\AverageRankResultSetAPITransformer;
use App\DataTransformer\API\RatingResultSetAPITransformer;
use App\Service\Homepage\AverageRankFieldResultSetService;
use App\Service\Homepage\RatingFieldResultSetService;
use NumberFormatter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class APIController
 * @package App\Controller\API
 */
class APIController extends AbstractAPIController
{
    /**
     * @var AverageRankFieldResultSetService
     */
    protected $averageRankFieldResultSetService;

    /**
     * @var RatingFieldResultSetService
     */
    protected $ratingFieldResultSetService;

    /**
     * @var AverageRankResultSetAPITransformer
     */
    protected $rankResultSetAPITransformer;

    /**
     * @var RatingResultSetAPITransformer
     */
    protected $ratingResultSetAPITransformer;

    public function __construct(
        AverageRankFieldResultSetService $averageRankFieldResultSetService,
        RatingFieldResultSetService $ratingFieldResultSetService,
        AverageRankResultSetAPITransformer $rankResultSetAPITransformer,
        RatingResultSetAPITransformer $ratingResultSetAPITransformer
    ) {
        $this->averageRankFieldResultSetService = $averageRankFieldResultSetService;
        $this->ratingFieldResultSetService = $ratingFieldResultSetService;
        $this->rankResultSetAPITransformer = $rankResultSetAPITransformer;
        $this->ratingResultSetAPITransformer = $ratingResultSetAPITransformer;
    }

    /**
     * @Route("/api/avg_rank", name="api_avg_rang", methods={"GET"})
     */
    public function getAverageRankFieldResultSet(Request $request): JsonResponse
    {
        $oddsEnabled = $request->get('odds');
        $limit = $request->get('limit');
        $offset = $request->get('offset');
        if ($oddsEnabled == null || $oddsEnabled == "null") {
            $oddsEnabled = false;
        }

        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $rs = $this->averageRankFieldResultSetService->generateAvgRankFieldResultSet($request, $oddsEnabled, $limit, $offset);
        $transformedData = $this->rankResultSetAPITransformer->transform($rs);

        return $this->response([
            'data' => $transformedData,
            'absoluteTotal' => $formatter->formatCurrency($rs->getAbsoluteTotal(), 'USD'),
            'totalProfit' => $formatter->formatCurrency($rs->getTotalProfit(), 'USD'),
            'totalLoss' => '-'.$formatter->formatCurrency($rs->getTotalLoss(), 'USD'),
            'cssClass' => ($rs->getAbsoluteTotal() > 0)?'alert-success':'alert-danger'
        ], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/api/rating", name="api_rating", methods={"GET"})
     */
    public function getRatingFieldResultSet(Request $request): JsonResponse
    {
        $oddsEnabled = $request->get('odds');
        $limit = $request->get('limit');
        $offset = $request->get('offset');
        if ($oddsEnabled == null || $oddsEnabled == "null") {
            $oddsEnabled = false;
        }

        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $rs = $this->ratingFieldResultSetService->generateRatingFieldResultSet($request, $oddsEnabled, $limit, $offset);
        $transformedData = $this->ratingResultSetAPITransformer->transform($rs);

        return $this->response([
            'data' => $transformedData,
            'absoluteTotal' => $formatter->formatCurrency($rs->getAbsoluteTotal(), 'USD'),
            'totalProfit' => $formatter->formatCurrency($rs->getTotalProfit(), 'USD'),
            'totalLoss' => '-'.$formatter->formatCurrency($rs->getTotalLoss(), 'USD'),
            'cssClass' => ($rs->getAbsoluteTotal() > 0)?'alert-success':'alert-danger'
        ], JsonResponse::HTTP_OK);
    }
}