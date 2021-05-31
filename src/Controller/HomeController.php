<?php

namespace App\Controller;

use App\Service\HomeControllerDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @var HomeControllerDataService
     */
    protected $homeService;

    public function __construct(HomeControllerDataService $homeService)
    {
        $this->homeService = $homeService;
    }

    /**
     * @Route("/", name="index")
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function show(Request $request): Response
    {
        $oddsFilter = $request->get('odds');
        if ($oddsFilter === null) {
            $oddsFilter = false;
        }

        return $this->render('home.html.twig', [
            'avgRankFieldResultSet' => $this->homeService->generateAvgRankFieldResultSet($request, $oddsFilter),
            'ratingFieldResultSet' => $this->homeService->generateRatingFieldResultSet($request, $oddsFilter),
            'requestMode' => $request->query->get('mode')
        ]);
    }
}