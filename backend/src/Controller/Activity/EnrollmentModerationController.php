<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Controller\ApiController;
use App\Dto\Activity\RejectEnrollmentRequest;
use App\Dto\Enrollment\EnrollmentResponse;
use App\Entity\Enrollment;
use App\Repository\EnrollmentRepository;
use App\Security\Voter\EnrollmentVoter;
use App\Service\Activity\EnrollmentModerationService;
use App\Service\Formatter\ScheduleFormatter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/enrollments/{id}', name: 'api_enrollments_')]
final class EnrollmentModerationController extends ApiController
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments,
        private readonly EnrollmentModerationService $moderation,
        private readonly ScheduleFormatter $scheduleFormatter,
    ) {
    }

    #[Route('/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(string $id): JsonResponse
    {
        $enrollment = $this->findModeratable($id);
        $this->moderation->confirm($enrollment);

        return $this->json(EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter));
    }

    #[Route('/reject', name: 'reject', methods: ['POST'])]
    public function reject(
        string $id,
        #[MapRequestPayload] ?RejectEnrollmentRequest $request = null,
    ): JsonResponse {
        $enrollment = $this->findModeratable($id);
        $this->moderation->reject($enrollment, $request?->reason);

        return $this->json(EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter));
    }

    /**
     * Права проверяются до перехода, чтобы «нет прав» (403) было важнее недопустимого статуса (409).
     */
    private function findModeratable(string $id): Enrollment
    {
        $enrollment = $this->enrollments->findOneWithDetails($id)
            ?? throw $this->createNotFoundException('Заявка не найдена');

        $this->denyAccessUnlessGranted(
            EnrollmentVoter::MODERATE,
            $enrollment,
            EnrollmentVoter::DENIED_MESSAGES[EnrollmentVoter::MODERATE],
        );

        return $enrollment;
    }
}
