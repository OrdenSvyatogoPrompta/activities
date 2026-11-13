<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Dto\Activity\RejectEnrollmentRequest;
use App\Entity\Enrollment;
use App\Security\Voter\EnrollmentVoter;
use App\Service\Activity\EnrollmentModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Подтверждение и отклонение входящих заявок (раздел 4, «Группа G»): владелец кружка,
 * для кружков без владельца — только ROLE_ADMIN (админ может любые).
 *
 * Ошибки: 404 — заявки нет, 403 — нет прав, 409 — недопустимый переход статуса.
 */
#[Route('/api/enrollments/{id}', name: 'api_enrollments_')]
final class EnrollmentModerationController extends AbstractController
{
    private const array JSON_CONTEXT = ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE];

    public function __construct(
        private readonly EnrollmentModerationService $moderation,
    ) {
    }

    #[Route('/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(string $id): JsonResponse
    {
        $enrollment = $this->findModeratable($id);

        return $this->json($this->moderation->confirm($enrollment), context: self::JSON_CONTEXT);
    }

    #[Route('/reject', name: 'reject', methods: ['POST'])]
    public function reject(
        string $id,
        #[MapRequestPayload] ?RejectEnrollmentRequest $request = null,
    ): JsonResponse {
        $enrollment = $this->findModeratable($id);

        return $this->json($this->moderation->reject($enrollment, $request?->reason), context: self::JSON_CONTEXT);
    }

    private function findModeratable(string $id): Enrollment
    {
        $enrollment = $this->moderation->find($id)
            ?? throw $this->createNotFoundException('Заявка не найдена');

        $this->denyAccessUnlessGranted(
            EnrollmentVoter::MODERATE,
            $enrollment,
            'Подтверждать и отклонять заявку может только владелец кружка или администратор',
        );

        return $enrollment;
    }
}
