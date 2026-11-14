<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\Enrollment;
use App\Service\Enrollment\EnrollmentTransitionApplier;
use App\Workflow\EnrollmentWorkflow;

final readonly class EnrollmentModerationService
{
    public function __construct(
        private EnrollmentTransitionApplier $transitions,
    ) {
    }

    public function confirm(Enrollment $enrollment): void
    {
        $this->transitions->apply($enrollment, EnrollmentWorkflow::TRANSITION_CONFIRM);
    }

    public function reject(Enrollment $enrollment, ?string $reason): void
    {
        $this->transitions->apply(
            $enrollment,
            EnrollmentWorkflow::TRANSITION_REJECT,
            static fn (Enrollment $enrollment) => $enrollment->setRejectReason($reason),
        );
    }
}
