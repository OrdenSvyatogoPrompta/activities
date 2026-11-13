<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Тестовый эндпоинт: проверка цепочки nginx -> php-fpm -> Symfony.
 */
final class PingController extends AbstractController
{
    #[Route('/api/ping', name: 'api_ping', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'time' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'php' => \PHP_VERSION,
        ]);
    }
}
