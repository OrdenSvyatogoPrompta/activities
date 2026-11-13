<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Dto\Activity\EnrollmentChildResponse;
use App\Dto\Activity\ModeratedEnrollmentResponse;
use App\Entity\Enrollment;
use App\Entity\Enum\DayOfWeek;
use App\Service\Formatter\ScheduleFormatter;

/**
 * Заявка → ответ в формате `Enrollment` группы F (для confirm/reject).
 *
 * Временный дубль фабрики группы F; при слиянии веток заменить на её реализацию.
 */
final readonly class EnrollmentResponseFactory
{
    public function __construct(
        private ScheduleFormatter $scheduleFormatter,
    ) {
    }

    public function enrollment(Enrollment $enrollment): ModeratedEnrollmentResponse
    {
        $club = $enrollment->getClub();
        $branch = $enrollment->getBranch();
        $child = $enrollment->getChild();

        return new ModeratedEnrollmentResponse(
            id: $enrollment->getId()->toRfc4122(),
            clubId: $club->getSlug(),
            clubName: $club->getName(),
            branchId: $branch?->getId()->toRfc4122(),
            branchName: $branch?->getName(),
            schedule: null === $branch ? '' : $this->scheduleFormatter->formatBranch($branch),
            days: null === $branch ? [] : array_map(static fn (DayOfWeek $day): string => $day->value, $branch->getDays()),
            color: $club->getColor(),
            forChild: null === $child ? null : new EnrollmentChildResponse(
                id: $child->getId()->toRfc4122(),
                firstName: $child->getFirstName(),
                lastName: $child->getLastName(),
                age: $child->getAge(),
                gender: $child->getGender()->value,
            ),
            enrolledAt: $enrollment->getCreatedAt()->format('Y-m-d'),
            status: $enrollment->getStatus()->value,
            rejectReason: $enrollment->getRejectReason(),
        );
    }
}
