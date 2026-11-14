<?php

declare(strict_types=1);

namespace App\Controller\Enrollment;

use App\Controller\ApiController;
use App\Dto\Enrollment\CreateEnrollmentRequest;
use App\Dto\Enrollment\EnrollmentListQuery;
use App\Dto\Enrollment\EnrollmentResponse;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Service\Enrollment\EnrollmentService;
use App\Service\Formatter\ScheduleFormatter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class EnrollmentController extends ApiController
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ScheduleFormatter $scheduleFormatter,
    ) {
    }

    #[Route('/api/enrollments', name: 'api_enrollments_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(acceptFormat: 'json')] CreateEnrollmentRequest $request,
    ): JsonResponse {
        $enrollment = $this->enrollments->create($user, $request);

        return $this->json(EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter), Response::HTTP_CREATED);
    }

    #[Route('/api/me/enrollments', name: 'api_me_enrollments', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        #[MapQueryString] EnrollmentListQuery $query = new EnrollmentListQuery(),
    ): JsonResponse {
        $enrollments = $this->enrollments->listOwn($user, $query->statuses());

        return $this->json(array_map(
            fn (Enrollment $enrollment): EnrollmentResponse => EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter),
            $enrollments,
        ));
    }

    #[Route('/api/enrollments/{id}/cancel', name: 'api_enrollments_cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        $enrollment = $this->enrollments->cancel($id);

        return $this->json(EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter));
    }
}
