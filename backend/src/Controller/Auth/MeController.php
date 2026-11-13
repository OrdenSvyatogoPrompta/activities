<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\ApiController;
use App\Dto\Auth\UpdateProfileRequest;
use App\Entity\User;
use App\Service\Auth\ProfileService;
use App\Service\Auth\UserViewFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/me', name: 'api_me_')]
final class MeController extends ApiController
{
    public function __construct(
        private readonly UserViewFactory $views,
        private readonly ProfileService $profile,
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->views->create($user));
    }

    #[Route('', name: 'update', methods: ['PATCH'])]
    public function update(#[CurrentUser] User $user, #[MapRequestPayload] UpdateProfileRequest $request): JsonResponse
    {
        $this->profile->update($user, $request);

        return $this->json($this->views->create($user));
    }
}
