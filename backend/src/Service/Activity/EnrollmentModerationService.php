<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\Enrollment;
use App\Service\Enrollment\EnrollmentTransitionApplier;
use App\Workflow\EnrollmentWorkflow;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EnrollmentModerationService
{
    public function __construct(
        private EnrollmentTransitionApplier $transitions,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function confirm(Enrollment $enrollment): void
    {
        $this->transitions->apply($enrollment, EnrollmentWorkflow::TRANSITION_CONFIRM);
        $this->entityManager->flush();
    }

    public function reject(Enrollment $enrollment, ?string $reason): void
    {
        $this->transitions->apply($enrollment, EnrollmentWorkflow::TRANSITION_REJECT);
        $enrollment->setRejectReason($reason);
        $this->entityManager->flush();
    }
}
