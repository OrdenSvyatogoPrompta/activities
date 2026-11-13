<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Enrollment;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Права на переходы workflow `enrollment` (правило D6):
 * - confirm / reject — владелец кружка или ROLE_ADMIN (заявки в кружки без владельца — только админ);
 * - cancel — только автор заявки.
 *
 * Без аутентифицированного пользователя (консоль, фикстуры) переходы заблокированы.
 *
 * Как различить ошибки после `$workflow->apply()` → NotEnabledTransitionException:
 * блокер с кодом EnrollmentWorkflow::BLOCKED_ACCESS_DENIED — нет прав (403),
 * TransitionBlocker::BLOCKED_BY_MARKING — недопустимый переход из текущего статуса (409).
 * `$workflow->buildTransitionBlockerList($enrollment, 'confirm')` даёт тот же список без исключения.
 */
final class EnrollmentGuardListener
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    #[AsGuardListener(workflow: EnrollmentWorkflow::NAME, transition: EnrollmentWorkflow::TRANSITION_CONFIRM)]
    #[AsGuardListener(workflow: EnrollmentWorkflow::NAME, transition: EnrollmentWorkflow::TRANSITION_REJECT)]
    public function guardModeration(GuardEvent $event): void
    {
        $enrollment = $event->getSubject();
        \assert($enrollment instanceof Enrollment);

        $user = $this->currentUser();
        if (null === $user || !$enrollment->canBeModeratedBy($user)) {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Подтверждать и отклонять заявку может только владелец кружка или администратор',
                EnrollmentWorkflow::BLOCKED_ACCESS_DENIED,
            ));
        }
    }

    #[AsGuardListener(workflow: EnrollmentWorkflow::NAME, transition: EnrollmentWorkflow::TRANSITION_CANCEL)]
    public function guardCancel(GuardEvent $event): void
    {
        $enrollment = $event->getSubject();
        \assert($enrollment instanceof Enrollment);

        $user = $this->currentUser();
        if (null === $user || !$enrollment->canBeCancelledBy($user)) {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Отменить заявку может только её автор',
                EnrollmentWorkflow::BLOCKED_ACCESS_DENIED,
            ));
        }
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
