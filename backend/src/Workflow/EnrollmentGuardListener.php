<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Security\Voter\EnrollmentVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Без аутентифицированного пользователя (консоль, фикстуры) переходы заблокированы.
 */
#[AsGuardListener(workflow: EnrollmentWorkflow::NAME)]
final readonly class EnrollmentGuardListener
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        $attribute = EnrollmentVoter::TRANSITION_ATTRIBUTES[$event->getTransition()->getName()];

        if (!$this->security->isGranted($attribute, $event->getSubject())) {
            $event->addTransitionBlocker(new TransitionBlocker(
                EnrollmentVoter::DENIED_MESSAGES[$attribute],
                EnrollmentWorkflow::BLOCKED_ACCESS_DENIED,
            ));
        }
    }
}
