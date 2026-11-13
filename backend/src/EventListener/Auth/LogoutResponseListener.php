<?php

declare(strict_types=1);

namespace App\EventListener\Auth;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Выход всегда отвечает 204: gesdinet отвечает 200, а без cookie — 400.
 * Срабатывает последним и переносит cookie из предыдущего ответа.
 */
#[AsEventListener(event: LogoutEvent::class, priority: -1024, dispatcher: 'security.event_dispatcher.api')]
final class LogoutResponseListener
{
    /** Как в gesdinet_jwt_refresh_token.yaml. */
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
