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
 * lexik отдаёт `{ token }`; API — `{ accessToken, user }`, а для refresh — `{ accessToken }`.
 * Приоритет ниже, чем у слушателя gesdinet (0): тело собирается заново, без refresh-токена.
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

        $route = $this->requestStack->getMainRequest()?->attributes->get('_route');
        $user = $event->getUser();

        if (self::REFRESH_ROUTE !== $route && $user instanceof User) {
            $data['user'] = $this->views->createArray($user);
        }

        $event->setData($data);
    }
}
