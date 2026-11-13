<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Dto\Activity\ActivityBranchResponse;
use App\Dto\Activity\ActivityResponse;
use App\Dto\Activity\IncomingEnrollmentResponse;
use App\Dto\Activity\ScheduleSlotResponse;
use App\Entity\Activity;
use App\Entity\ActivityBranch;
use App\Entity\Enrollment;
use App\Entity\Enum\DayOfWeek;
use App\Entity\ScheduleSlot;
use App\Service\Formatter\AgeFormatter;
use App\Service\Formatter\PriceFormatter;
use App\Service\Formatter\ScheduleFormatter;

/**
 * Сущности активности → DTO ответа API (раздел 4, «Группа G»).
 */
final readonly class ActivityResponseFactory
{
    public function __construct(
        private PriceFormatter $priceFormatter,
        private AgeFormatter $ageFormatter,
        private ScheduleFormatter $scheduleFormatter,
    ) {
    }

    /**
     * @param list<Enrollment> $enrollments входящие заявки в кружок активности (без cancelled)
     */
    public function activity(Activity $activity, array $enrollments = []): ActivityResponse
    {
        return new ActivityResponse(
            id: $activity->getId()->toRfc4122(),
            name: $activity->getName(),
            category: $activity->getCategory()->value,
            emoji: $activity->getEmoji(),
            description: $activity->getDescription(),
            status: $activity->getStatus()->value,
            moderationComment: $activity->getModerationComment(),
            clubId: $activity->getClub()?->getSlug(),
            ageMin: $activity->getAgeMin(),
            ageMax: $activity->getAgeMax(),
            age: $this->ageFormatter->format($activity->getAgeMin(), $activity->getAgeMax()),
            visitType: $activity->getVisitType()->value,
            priceType: $activity->getPriceType()->value,
            priceAmount: $activity->getPriceAmount(),
            price: $this->priceFormatter->format($activity->getPriceType(), $activity->getPriceAmount()),
            tags: $activity->getTags(),
            createdAt: $activity->getCreatedAt()->format(\DATE_ATOM),
            branches: array_values(array_map($this->branch(...), $activity->getBranches()->toArray())),
            enrollments: array_values(array_map($this->incoming(...), $enrollments)),
        );
    }

    private function branch(ActivityBranch $branch): ActivityBranchResponse
    {
        $first = $branch->getFirstSlot();

        return new ActivityBranchResponse(
            id: $branch->getId()->toRfc4122(),
            name: $branch->getName(),
            address: $branch->getAddress(),
            phone: $branch->getPhone(),
            schedule: $this->scheduleFormatter->formatBranch($branch),
            days: array_map(static fn (DayOfWeek $day): string => $day->value, $branch->getDays()),
            timeFrom: $first?->getTimeFromString(),
            timeTo: $first?->getTimeToString(),
            slots: array_map(
                static fn (ScheduleSlot $slot): ScheduleSlotResponse => new ScheduleSlotResponse(
                    $slot->getDay()->value,
                    $slot->getTimeFromString(),
                    $slot->getTimeToString(),
                ),
                $branch->getSlots(),
            ),
        );
    }

    private function incoming(Enrollment $enrollment): IncomingEnrollmentResponse
    {
        $branch = $enrollment->getBranch();

        return new IncomingEnrollmentResponse(
            id: $enrollment->getId()->toRfc4122(),
            personName: $enrollment->getUser()->getName(),
            childName: $enrollment->getChild()?->getFirstName(),
            branchId: $branch?->getId()->toRfc4122(),
            branchName: $branch?->getName(),
            activityBranchId: $branch?->getSourceActivityBranch()?->getId()->toRfc4122(),
            enrolledAt: $enrollment->getCreatedAt()->format('Y-m-d'),
            status: $enrollment->getStatus()->value,
        );
    }
}
