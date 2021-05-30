<?php

namespace App\Controller\API;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

abstract class AbstractAPIController extends AbstractController
{
    protected function response(array $data, int $statusCode): JsonResponse
    {
        return new JsonResponse($data, $statusCode);
    }

    protected function error(Throwable $error, int $statusCode): JsonResponse
    {
        $message = $error->getMessage();

        return $this->response([
            'message' => $message,
            'code' => $statusCode
        ], $statusCode);
    }
}