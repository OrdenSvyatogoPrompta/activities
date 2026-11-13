<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class ApiController extends AbstractController
{
    /**
     * Кириллица без \u-экранирования, как в problem+json: ответы заметно короче.
     */
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        return parent::json($data, $status, $headers, $context + [
            'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE,
        ]);
    }
}
