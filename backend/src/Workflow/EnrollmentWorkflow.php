<?php

declare(strict_types=1);

namespace App\Workflow;

/**
 * Имена workflow `enrollment` (config/packages/workflow.yaml) — чтобы не писать строки в коде.
 *
 * Сервис: `state_machine.enrollment`; автовайринг — `WorkflowInterface $enrollmentStateMachine`
 * или `#[Target('enrollment')] WorkflowInterface $workflow`.
 */
final class EnrollmentWorkflow
{
    public const string NAME = 'enrollment';

    /** pending → confirmed; владелец кружка или ROLE_ADMIN */
    public const string TRANSITION_CONFIRM = 'confirm';

    /** pending → rejected; владелец кружка или ROLE_ADMIN */
    public const string TRANSITION_REJECT = 'reject';

    /** pending|confirmed → cancelled; только автор заявки */
    public const string TRANSITION_CANCEL = 'cancel';

    /** Код TransitionBlocker: у текущего пользователя нет прав на переход (→ 403). */
    public const string BLOCKED_ACCESS_DENIED = 'enrollment.access_denied';
}
