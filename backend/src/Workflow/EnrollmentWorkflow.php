<?php

declare(strict_types=1);

namespace App\Workflow;

/**
 * Имена workflow `enrollment` (config/packages/workflow.yaml).
 */
final class EnrollmentWorkflow
{
    public const string NAME = 'enrollment';

    public const string TRANSITION_CONFIRM = 'confirm';
    public const string TRANSITION_REJECT = 'reject';
    public const string TRANSITION_CANCEL = 'cancel';

    /** Код TransitionBlocker «нет прав» (→ 403). */
    public const string BLOCKED_ACCESS_DENIED = 'enrollment.access_denied';
}
