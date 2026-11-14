<?php

declare(strict_types=1);

namespace App\EventListener\Auth;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Выход всегда отвечает 204: gesdinet отвечает 200, а без cookie — 400.
 * Срабатывает последним и переносит очистку cookie, которую выставил gesdinet.
 */
#[AsEventListener(event: LogoutEvent::class, priority: -1024, dispatcher: 'security.event_dispatcher.api')]
final class LogoutResponseListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);

        foreach ($event->getResponse()?->headers->getCookies() ?? [] as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $event->setResponse($response);
    }
}
