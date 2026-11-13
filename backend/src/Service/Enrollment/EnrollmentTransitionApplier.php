<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Entity\Enrollment;
use App\Workflow\EnrollmentWorkflow;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Применяет переход workflow и переводит отказ в HTTP: нет прав — 403,
 * переход недопустим из текущего статуса — 409. Flush делает вызывающий.
 */
final readonly class EnrollmentTransitionApplier
{
    private const array TRANSITION_LABELS = [
        EnrollmentWorkflow::TRANSITION_CONFIRM => 'подтвердить',
        EnrollmentWorkflow::TRANSITION_REJECT => 'отклонить',
        EnrollmentWorkflow::TRANSITION_CANCEL => 'отменить',
    ];

    private const array STATUS_LABELS = [
        'pending' => 'на рассмотрении',
        'confirmed' => 'подтверждена',
        'rejected' => 'отклонена',
        'cancelled' => 'отменена',
    ];

    public function __construct(
        private WorkflowInterface $enrollmentStateMachine,
    ) {
    }

    public function apply(Enrollment $enrollment, string $transition): void
    {
        try {
            $this->enrollmentStateMachine->apply($enrollment, $transition);
        } catch (NotEnabledTransitionException $e) {
            throw self::toHttpException($e, $enrollment);
        }
    }

    private static function toHttpException(NotEnabledTransitionException $e, Enrollment $enrollment): HttpException
    {
        $blockers = $e->getTransitionBlockerList();

        foreach ($blockers as $blocker) {
            if (EnrollmentWorkflow::BLOCKED_ACCESS_DENIED === $blocker->getCode()) {
                return new AccessDeniedHttpException($blocker->getMessage(), $e);
            }
        }

        $onlyByMarking = !$blockers->isEmpty();
        foreach ($blockers as $blocker) {
            $onlyByMarking = $onlyByMarking && TransitionBlocker::BLOCKED_BY_MARKING === $blocker->getCode();
        }

        if ($onlyByMarking) {
            return new ConflictHttpException(\sprintf(
                'Нельзя %s заявку: она %s',
                self::TRANSITION_LABELS[$e->getTransitionName()] ?? 'изменить',
                self::STATUS_LABELS[$enrollment->getStatus()->value],
            ), $e);
        }

        return new AccessDeniedHttpException('Переход статуса заявки запрещён', $e);
    }
}
