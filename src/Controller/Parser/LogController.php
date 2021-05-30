<?php

namespace App\Controller\Parser;

use App\Service\PrettyLogger;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class LogController extends AbstractController
{
    protected $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @Route("/log/main", name="log_main")
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function obtainMainLog(): JsonResponse
    {
        if ($this->session->isStarted()) {
            $this->session->save();
        }

        try {
            $logger = new PrettyLogger(__FILE__, "main_log.txt");
        } catch (Exception $e) {
            die($e->getMessage());
        }

        return new JsonResponse($logger->getLastLog());
    }
}