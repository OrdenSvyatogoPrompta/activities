<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Entity\Enrollment;
use App\Repository\EnrollmentRepository;
use App\Security\Voter\EnrollmentVoter;
use App\Workflow\EnrollmentWorkflow;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Единая точка переходов заявки: не видит заявку — 404, видит, но нет прав — 403,
 * переход недопустим из текущего статуса — 409.
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
        private EntityManagerInterface $entityManager,
        private EnrollmentRepository $enrollments,
        private Security $security,
    ) {
    }

    /**
     * @param (\Closure(Enrollment): void)|null $change дополнительные изменения в той же транзакции
     */
    public function apply(string $id, string $transition, ?\Closure $change = null): Enrollment
    {
        $enrollment = $this->enrollments->findOneWithDetails($id);
        if (null === $enrollment || !$this->security->isGranted(EnrollmentVoter::VIEW, $enrollment)) {
            throw new NotFoundHttpException('Заявка не найдена');
        }

        // Права до перехода: workflow сначала проверяет статус, а «нет прав» (403) важнее 409
        $attribute = EnrollmentVoter::TRANSITION_ATTRIBUTES[$transition];
        if (!$this->security->isGranted($attribute, $enrollment)) {
            throw new AccessDeniedHttpException(EnrollmentVoter::DENIED_MESSAGES[$attribute]);
        }

        // Статус перечитывается под блокировкой строки, чтобы параллельные переходы не затёрли друг друга.
        // Отказ бросаем после транзакции: исключение внутри wrapInTransaction() закрыло бы EntityManager.
        $error = $this->entityManager->wrapInTransaction(function () use ($enrollment, $transition, $change): ?HttpException {
            $this->entityManager->refresh($enrollment, LockMode::PESSIMISTIC_WRITE);

            try {
                $this->enrollmentStateMachine->apply($enrollment, $transition);
            } catch (NotEnabledTransitionException $e) {
                return self::toHttpException($e, $enrollment);
            }

            if (null !== $change) {
                $change($enrollment);
            }

            return null;
        });

        if (null !== $error) {
            throw $error;
        }

        return $enrollment;
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
