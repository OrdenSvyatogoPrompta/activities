<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Dto\Activity\CreateActivityRequest;
use App\Entity\User;
use App\Service\Activity\ActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Активности организатора в ЛК (раздел 4, «Группа G»).
 */
#[Route('/api/me/activities', name: 'api_me_activities_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class MyActivityController extends AbstractController
{
    /** Ответы — с кириллицей без \u-экранирования, как problem+json. */
    private const array JSON_CONTEXT = ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE];

    public function __construct(
        private readonly ActivityService $activities,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->activities->listFor($user), context: self::JSON_CONTEXT);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $activity = $this->activities->getFor($id, $user)
            ?? throw $this->createNotFoundException('Активность не найдена');

        return $this->json($activity, context: self::JSON_CONTEXT);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateActivityRequest $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        return $this->json($this->activities->create($request, $user), Response::HTTP_CREATED, context: self::JSON_CONTEXT);
    }
}
