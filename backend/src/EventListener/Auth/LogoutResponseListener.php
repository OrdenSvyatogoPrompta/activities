<?php

declare(strict_types=1);

namespace App\EventListener\Auth;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * POST /api/auth/logout → всегда `204 No Content` с удалением cookie refresh-токена.
 *
 * Сам refresh-токен удаляет из БД слушатель выхода gesdinet, но отвечает он
 * `200 { code, message }`, а без cookie — `400`. Выход идемпотентен: ответ один и тот же,
 * была cookie или нет. Слушатель срабатывает последним (после gesdinet и
 * DefaultLogoutListener) и переносит из предыдущего ответа выставленные cookie.
 *
 * Слушатель висит на диспетчере firewall `api` (там и бросается LogoutEvent).
 */
#[AsEventListener(event: LogoutEvent::class, priority: -1024, dispatcher: 'security.event_dispatcher.api')]
final class LogoutResponseListener
{
    /** Совпадает с gesdinet_jwt_refresh_token.yaml: token_parameter_name и cookie.path. */
    private const string COOKIE_NAME = 'refresh_token';
    private const string COOKIE_PATH = '/api/auth';

    public function __invoke(LogoutEvent $event): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $cleared = false;

        foreach ($event->getResponse()?->headers->getCookies() ?? [] as $cookie) {
            $response->headers->setCookie($cookie);
            $cleared = $cleared || self::COOKIE_NAME === $cookie->getName();
        }

        if (!$cleared) {
            $response->headers->clearCookie(self::COOKIE_NAME, self::COOKIE_PATH, null, true, true, Cookie::SAMESITE_STRICT);
        }

        $event->setResponse($response);
    }
}
