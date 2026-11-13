<?php

declare(strict_types=1);

namespace App\Dto\Enrollment;

use App\Dto\Children\ChildView;
use App\Entity\Enrollment;
use App\Service\Formatter\ScheduleFormatter;

final readonly class EnrollmentResponse
{
    /**
     * @param list<string> $days
     */
    public function __construct(
        public string $id,
        public string $clubId,
        public string $clubName,
        public ?string $branchId,
        public ?string $branchName,
        public string $schedule,
        public array $days,
        public string $color,
        public ?ChildView $forChild,
        public string $enrolledAt,
        public string $status,
        public ?string $rejectReason,
    ) {
    }

    /**
     * Связи должны быть загружены заранее (EnrollmentRepository), иначе N+1 по слотам.
     */
    public static function fromEntity(Enrollment $enrollment, ScheduleFormatter $scheduleFormatter): self
    {
        $club = $enrollment->getClub();
        $branch = $enrollment->getBranch();
        $child = $enrollment->getChild();

        return new self(
            id: $enrollment->getId()->toRfc4122(),
            clubId: $club->getSlug(),
            clubName: $club->getName(),
            branchId: $branch?->getId()->toRfc4122(),
            branchName: $branch?->getName(),
            schedule: null === $branch ? '' : $scheduleFormatter->formatBranch($branch),
            days: null === $branch ? [] : array_column($branch->getDays(), 'value'),
            color: $club->getColor(),
            forChild: null === $child ? null : ChildView::fromEntity($child),
            enrolledAt: $enrollment->getCreatedAt()->format('Y-m-d'),
            status: $enrollment->getStatus()->value,
            rejectReason: $enrollment->getRejectReason(),
        );
    }
}
