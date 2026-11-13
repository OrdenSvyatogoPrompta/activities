<?php

declare(strict_types=1);

namespace App\EventListener\Auth;

use App\Entity\User;
use App\Service\Auth\UserViewFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Тело успешного ответа аутентификации: lexik отдаёт `{ token }`, контракт API —
 * - вход и регистрация: `{ accessToken, user }`;
 * - POST /api/auth/refresh: `{ accessToken }`.
 *
 * Событие бросает обработчик успеха lexik и при json_login, и при refresh_jwt (gesdinet),
 * и в TokenIssuer после регистрации. Приоритет ниже, чем у слушателя gesdinet (0), который
 * ставит cookie с refresh-токеном: тело собирается заново, и refresh-токен в него не попадает,
 * даже если gesdinet его туда положил.
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, priority: -16)]
final readonly class AuthenticationSuccessListener
{
    private const string REFRESH_ROUTE = 'api_auth_refresh';

    public function __construct(
        private UserViewFactory $views,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $data = ['accessToken' => $event->getData()['token'] ?? null];

        // Маршрут определяет роутер ещё до firewall, поэтому `_route` уже есть
        $route = $this->requestStack->getMainRequest()?->attributes->get('_route');
        $user = $event->getUser();

        if (self::REFRESH_ROUTE !== $route && $user instanceof User) {
            $data['user'] = $this->views->createArray($user);
        }

        $event->setData($data);
    }
}
