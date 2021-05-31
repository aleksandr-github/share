<?php

namespace App\Controller\Parser;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParserController extends AbstractController
{
    /**
     * @Route("/parser", name="parser_main")
     *
     * @return Response
     */
    public function scrape(): Response
    {
        return $this->render('parser/scrape.html.twig');
    }

    /**
     * @Route("/parser/scrape", methods={"POST"}, name="parser_scrape_data")
     *
     * @return JsonResponse
     */
    public function scrapeData(): JsonResponse
    {
        return new JsonResponse([
            'code' => 404,
            'message' => 'Not implemented'
        ]);
    }
}