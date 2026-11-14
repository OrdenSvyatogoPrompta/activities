<?php

declare(strict_types=1);

namespace App\EventListener\Auth;

use App\Entity\User;
use App\Service\Auth\UserViewFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * lexik отдаёт `{ token }`; API на login и refresh — `{ accessToken, user }`.
 * Приоритет ниже, чем у слушателя gesdinet (0): тело собирается заново, без refresh-токена.
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, priority: -16)]
final readonly class AuthenticationSuccessListener
{
    public function __construct(
        private UserViewFactory $views,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        \assert($user instanceof User);

        $event->setData([
            'accessToken' => $event->getData()['token'] ?? null,
            'user' => $this->views->createArray($user),
        ]);
    }
}
