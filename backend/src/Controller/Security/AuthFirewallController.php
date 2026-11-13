<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Маршруты, которые обслуживает firewall `api` (config/packages/security.yaml).
 *
 * Роутер срабатывает раньше firewall и без маршрута ответил бы 404, поэтому маршруты
 * нужны, но до действий запрос доходит только в нештатных случаях: успешный и неуспешный
 * вход отвечает json_login (обработчики lexik), refresh — аутентификатор gesdinet.
 * Logout-маршрут создаёт security.route_loader.logout.
 */
final class AuthFirewallController extends AbstractController
{
    /**
     * json_login берёт только JSON-запросы; остальные доходят сюда.
     */
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new UnsupportedMediaTypeHttpException('Ожидается тело запроса в формате JSON (Content-Type: application/json)');
    }

    /**
     * Аутентификатор refresh_jwt отвечает на любой запрос по этому пути.
     */
    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): never
    {
        throw new \LogicException('Запрос должен быть перехвачен аутентификатором refresh_jwt firewall "api".');
    }
}
