<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Dto\Activity\ModeratedEnrollmentResponse;
use App\Entity\Enrollment;
use App\Entity\Enum\EnrollmentStatus;
use App\Repository\EnrollmentRepository;
use App\Workflow\EnrollmentWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Подтверждение и отклонение заявок владельцем кружка или администратором через
 * workflow `enrollment`. Права проверяет контроллер (EnrollmentVoter::MODERATE) до вызова;
 * guard workflow проверяет их повторно.
 */
final readonly class EnrollmentModerationService
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private WorkflowInterface $enrollmentStateMachine,
        private EntityManagerInterface $entityManager,
        private EnrollmentResponseFactory $responses,
    ) {
    }

    /**
     * Заявка по id со связями; некорректный или неизвестный id — null (→ 404).
     */
    public function find(string $id): ?Enrollment
    {
        return $this->enrollments->findOneWithDetails($id);
    }

    /**
     * pending → confirmed.
     *
     * @throws ConflictHttpException     недопустимый переход из текущего статуса (409)
     * @throws AccessDeniedHttpException нет прав (403)
     */
    public function confirm(Enrollment $enrollment): ModeratedEnrollmentResponse
    {
        $this->apply($enrollment, EnrollmentWorkflow::TRANSITION_CONFIRM);
        $this->entityManager->flush();

        return $this->responses->enrollment($enrollment);
    }

    /**
     * pending → rejected, с необязательной причиной.
     *
     * @throws ConflictHttpException     недопустимый переход из текущего статуса (409)
     * @throws AccessDeniedHttpException нет прав (403)
     */
    public function reject(Enrollment $enrollment, ?string $reason): ModeratedEnrollmentResponse
    {
        $this->apply($enrollment, EnrollmentWorkflow::TRANSITION_REJECT);
        $enrollment->setRejectReason($reason);
        $this->entityManager->flush();

        return $this->responses->enrollment($enrollment);
    }

    private function apply(Enrollment $enrollment, string $transition): void
    {
        $blockers = $this->enrollmentStateMachine->buildTransitionBlockerList($enrollment, $transition);

        foreach ($blockers as $blocker) {
            if (EnrollmentWorkflow::BLOCKED_ACCESS_DENIED === $blocker->getCode()) {
                throw new AccessDeniedHttpException($blocker->getMessage());
            }
        }

        if (!$blockers->isEmpty()) {
            $verb = EnrollmentWorkflow::TRANSITION_CONFIRM === $transition ? 'подтвердить' : 'отклонить';

            throw new ConflictHttpException(\sprintf('Нельзя %s заявку в статусе «%s»', $verb, self::statusLabel($enrollment->getStatus())));
        }

        $this->enrollmentStateMachine->apply($enrollment, $transition);
    }

    private static function statusLabel(EnrollmentStatus $status): string
    {
        return match ($status) {
            EnrollmentStatus::Pending => 'На рассмотрении',
            EnrollmentStatus::Confirmed => 'Подтверждена',
            EnrollmentStatus::Rejected => 'Отклонена',
            EnrollmentStatus::Cancelled => 'Отменена',
        };
    }
}
