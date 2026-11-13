<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\Admin\ActivityListQuery;
use App\Dto\Admin\ActivityView;
use App\Dto\Admin\RejectActivityPayload;
use App\Entity\Activity;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\Admin\ActivityModerator;
use App\Service\Admin\AdminViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Модерация активностей (группа H). `/api/admin` закрыт ROLE_ADMIN в access_control;
 * IsGranted здесь — на случай изменения правил firewall.
 */
#[Route('/api/admin/activities', name: 'api_admin_activities_')]
#[IsGranted('ROLE_ADMIN')]
final class ActivityModerationController extends AbstractController
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityModerator $moderator,
        private readonly AdminViewFactory $views,
    ) {
    }

    /**
     * Очередь модерации (`?status=pending` по умолчанию), старые первыми.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ActivityListQuery $query = new ActivityListQuery(),
    ): JsonResponse {
        return $this->json($this->views->activities($this->activityRepository->findByStatus($query->statusEnum())));
    }

    /**
     * Одобрение: кружок публикуется в каталоге, `clubId` — его slug.
     */
    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(string $id, #[CurrentUser] User $admin): JsonResponse
    {
        $activity = $this->findActivity($id);
        $this->moderator->approve($activity, $admin);

        return $this->json($this->views->activity($activity));
    }

    /**
     * Отклонение с обязательным комментарием для автора.
     */
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
            ?? throw new NotFoundHttpException('Активность не найдена');
    }
}
