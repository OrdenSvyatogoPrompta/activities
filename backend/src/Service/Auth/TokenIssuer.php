<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выдаёт токены после регистрации тем же обработчиком lexik, что и при входе:
 * так срабатывают те же слушатели (cookie refresh-токена, форма тела).
 */
final readonly class TokenIssuer
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private AuthenticationSuccessHandler $successHandler,
    ) {
    }

    public function issue(User $user): Response
    {
        return $this->successHandler->handleAuthenticationSuccess($user);
    }
}
