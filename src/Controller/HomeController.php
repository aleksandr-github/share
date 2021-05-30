<?php

namespace App\Controller;

use App\Service\Homepage\AverageRankFieldResultSetService;
use App\Service\Homepage\RatingFieldResultSetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @var AverageRankFieldResultSetService
     */
    protected $averageRankFieldResultSetService;

    /**
     * @var RatingFieldResultSetService
     */
    protected $ratingFieldResultSetService;

    public function __construct(
        AverageRankFieldResultSetService $averageRankFieldResultSetService,
        RatingFieldResultSetService $ratingFieldResultSetService
    ) {
        $this->averageRankFieldResultSetService = $averageRankFieldResultSetService;
        $this->ratingFieldResultSetService = $ratingFieldResultSetService;
    }

    /**
     * @Route("/", name="index")
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function show(Request $request): Response
    {
        return $this->render('home.html.twig', [
            'requestMode' => $request->query->get('mode')
        ]);
    }
}