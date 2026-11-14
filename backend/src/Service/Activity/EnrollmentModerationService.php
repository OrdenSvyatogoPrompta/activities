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

    public function confirm(string $id): Enrollment
    {
        return $this->transitions->apply($id, EnrollmentWorkflow::TRANSITION_CONFIRM);
    }

    public function reject(string $id, ?string $reason): Enrollment
    {
        return $this->transitions->apply(
            $id,
            EnrollmentWorkflow::TRANSITION_REJECT,
            static fn (Enrollment $enrollment) => $enrollment->setRejectReason($reason),
        );
    }
}
