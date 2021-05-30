<?php

namespace App\Controller\Parser;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ResultsController extends AbstractController
{
    /**
     * @Route("/parser/results", name="parser_results")
     *
     * @return Response
     */
    public function results(): Response
    {
        return $this->render('parser/results.html.twig');
    }

    /**
     * @Route("/parser/parse_results", methods={"POST"}, name="parser_parse_results")
     *
     * @return JsonResponse
     */
    public function parseResults(): JsonResponse
    {
        return new JsonResponse([
            'code' => 404,
            'message' => "Not implemented"
        ]);
    }
}