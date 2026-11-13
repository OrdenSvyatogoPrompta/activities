<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Запросы обслуживает firewall `api`, но роутер срабатывает раньше и без маршрутов ответил бы 404.
 */
final class AuthFirewallController extends AbstractController
{
    /**
     * Сюда доходят только не-JSON запросы: json_login их пропускает.
     */
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new UnsupportedMediaTypeHttpException('Ожидается тело запроса в формате JSON (Content-Type: application/json)');
    }

    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): never
    {
        throw new \LogicException('Запрос должен быть перехвачен аутентификатором refresh_jwt firewall "api".');
    }
}
