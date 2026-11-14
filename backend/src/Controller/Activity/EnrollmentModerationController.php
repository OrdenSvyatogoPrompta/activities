<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Controller\ApiController;
use App\Dto\Activity\RejectEnrollmentRequest;
use App\Dto\Enrollment\EnrollmentResponse;
use App\Service\Activity\EnrollmentModerationService;
use App\Service\Formatter\ScheduleFormatter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/enrollments/{id}', name: 'api_enrollments_')]
final class EnrollmentModerationController extends ApiController
{
    public function __construct(
        private readonly EnrollmentModerationService $moderation,
        private readonly ScheduleFormatter $scheduleFormatter,
    ) {
    }

    #[Route('/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(string $id): JsonResponse
    {
        $enrollment = $this->moderation->confirm($id);

        return $this->json(EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter));
    }

    #[Route('/reject', name: 'reject', methods: ['POST'])]
    public function reject(
        string $id,
        #[MapRequestPayload] ?RejectEnrollmentRequest $request = null,
    ): JsonResponse {
        $enrollment = $this->moderation->reject($id, $request?->reason);

        return $this->json(EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter));
    }
}
