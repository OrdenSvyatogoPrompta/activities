<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Dto\Auth\RegisterRequest;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\TokenIssuer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/auth/register { name, email, password } → 201 { accessToken, user } + cookie refresh_token.
 *
 * Вход (POST /api/auth/login), обновление (POST /api/auth/refresh) и выход (POST /api/auth/logout)
 * обслуживает firewall `api`, см. App\Controller\Security\AuthFirewallController и слушатели
 * в App\EventListener\Auth.
 */
final class RegisterController extends AbstractController
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly TokenIssuer $tokens,
    ) {
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RegisterRequest $request): Response
    {
        $user = $this->registration->register($request);

        return $this->tokens->issue($user)->setStatusCode(Response::HTTP_CREATED);
    }
}
