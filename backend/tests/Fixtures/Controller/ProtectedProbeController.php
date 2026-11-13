<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Защищённые маршруты для smoke-тестов платформы, пока в API нет настоящих
 * (подключается только в test-окружении, см. config/services.yaml).
 */
final class ProtectedProbeController extends AbstractController
{
    #[Route('/api/_test/protected', name: 'test_protected', methods: ['GET'])]
    public function protected(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route('/api/admin/_test/protected', name: 'test_admin_protected', methods: ['GET'])]
    public function admin(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }
}
