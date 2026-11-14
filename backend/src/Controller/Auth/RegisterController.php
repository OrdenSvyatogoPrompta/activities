<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\ApiController;
use App\Dto\Auth\RegisterRequest;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\TokenIssuer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Вход, обновление токена и выход обслуживает firewall `api` (AuthFirewallController).
 */
final class RegisterController extends ApiController
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
