<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выдаёт токены без json_login (после регистрации) тем же путём, что и при входе:
 * обработчик успеха lexik создаёт access-токен и бросает событие
 * `lexik_jwt_authentication.on_authentication_success`, на котором gesdinet ставит cookie
 * с refresh-токеном, а AuthenticationSuccessListener приводит тело к `{ accessToken, user }`.
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
