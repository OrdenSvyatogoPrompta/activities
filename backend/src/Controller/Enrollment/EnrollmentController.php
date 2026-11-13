<?php

declare(strict_types=1);

namespace App\Controller\Enrollment;

use App\Dto\Enrollment\CreateEnrollmentRequest;
use App\Dto\Enrollment\EnrollmentListQuery;
use App\Dto\Enrollment\EnrollmentResponse;
use App\Entity\User;
use App\Service\Enrollment\EnrollmentService;
use App\Service\Formatter\ScheduleFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Заявки пользователя на запись в кружки (группа F). Доступ — только с access-токеном
 * (access_control `^/api` → IS_AUTHENTICATED_FULLY), здесь это продублировано атрибутом.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class EnrollmentController extends AbstractController
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ScheduleFormatter $scheduleFormatter,
    ) {
    }

    /**
     * Подать заявку: `{ clubId, branchId?, childId? }` → 201, `status: pending`.
     */
    #[Route('/api/enrollments', name: 'api_enrollments_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(acceptFormat: 'json')] CreateEnrollmentRequest $request,
    ): JsonResponse {
        $enrollment = $this->enrollments->create($user, $request);

        return $this->json(EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter), Response::HTTP_CREATED);
    }

    /**
     * Свои заявки, новые первыми; `?status=pending[,confirmed…]`, по умолчанию все, кроме cancelled.
     */
    #[Route('/api/me/enrollments', name: 'api_me_enrollments', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        #[MapQueryString] EnrollmentListQuery $query = new EnrollmentListQuery(),
    ): JsonResponse {
        $enrollments = $this->enrollments->listOwn($user, $query->statuses());

        return $this->json(EnrollmentResponse::fromEntities($enrollments, $this->scheduleFormatter));
    }

    /**
     * Отменить свою заявку (pending|confirmed → cancelled) → 200 с обновлённой заявкой.
     */
    #[Route('/api/enrollments/{id}/cancel', name: 'api_enrollments_cancel', methods: ['POST'])]
    public function cancel(#[CurrentUser] User $user, string $id): JsonResponse
    {
        $enrollment = $this->enrollments->cancel($user, $id);

        return $this->json(EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter));
    }
}
