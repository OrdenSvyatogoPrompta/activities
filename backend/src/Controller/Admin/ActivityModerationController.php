<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\ApiController;
use App\Dto\Admin\ActivityListQuery;
use App\Dto\Admin\RejectActivityPayload;
use App\Entity\Activity;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\Admin\ActivityModerator;
use App\Service\Admin\AdminViewFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/activities', name: 'api_admin_activities_')]
final class ActivityModerationController extends ApiController
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityModerator $moderator,
        private readonly AdminViewFactory $views,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ActivityListQuery $query = new ActivityListQuery(),
    ): JsonResponse {
        return $this->json($this->views->activities($this->activityRepository->findByStatus($query->statusEnum())));
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(string $id, #[CurrentUser] User $admin): JsonResponse
    {
        $activity = $this->findActivity($id);
        $this->moderator->approve($activity, $admin);

        return $this->json($this->views->activity($activity));
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(
        string $id,
        #[MapRequestPayload] RejectActivityPayload $payload,
        #[CurrentUser] User $admin,
    ): JsonResponse {
        $activity = $this->findActivity($id);
        $this->moderator->reject($activity, $payload->comment, $admin);

        return $this->json($this->views->activity($activity));
    }

    private function findActivity(string $id): Activity
    {
        return $this->activityRepository->findOneWithDetails($id)
            ?? throw $this->createNotFoundException('Активность не найдена');
    }
}
