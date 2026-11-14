<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Controller\ApiController;
use App\Dto\Activity\CreateActivityRequest;
use App\Entity\User;
use App\Service\Activity\ActivityService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/me/activities', name: 'api_me_activities_')]
final class MyActivityController extends ApiController
{
    public function __construct(
        private readonly ActivityService $activities,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->activities->listFor($user));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $activity = $this->activities->getFor($id, $user)
            ?? throw $this->createNotFoundException('Активность не найдена');

        return $this->json($activity);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateActivityRequest $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        return $this->json($this->activities->create($request, $user), Response::HTTP_CREATED);
    }
}
