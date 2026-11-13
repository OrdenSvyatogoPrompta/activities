<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Dto\Activity\ActivityBranchResponse;
use App\Dto\Activity\ActivityResponse;
use App\Dto\Activity\IncomingEnrollmentResponse;
use App\Dto\Catalog\ScheduleSlotView;
use App\Entity\Activity;
use App\Entity\ActivityBranch;
use App\Entity\Enrollment;
use App\Repository\EnrollmentRepository;
use App\Service\Formatter\AgeFormatter;
use App\Service\Formatter\PriceFormatter;
use App\Service\Formatter\ScheduleFormatter;

final readonly class ActivityResponseFactory
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private PriceFormatter $priceFormatter,
        private AgeFormatter $ageFormatter,
        private ScheduleFormatter $scheduleFormatter,
    ) {
    }

    /**
     * Входящие заявки всех кружков загружаются одним запросом.
     *
     * @param list<Activity> $activities
     *
     * @return list<ActivityResponse>
     */
    public function createList(array $activities): array
    {
        $clubs = array_values(array_filter(array_map(static fn (Activity $activity) => $activity->getClub(), $activities)));

        $incoming = [];
        foreach ($this->enrollments->findIncomingForClubs($clubs) as $enrollment) {
            $incoming[$enrollment->getClub()->getId()->toRfc4122()][] = $enrollment;
        }

        return array_map(
            fn (Activity $activity): ActivityResponse => $this->create(
                $activity,
                $incoming[$activity->getClub()?->getId()->toRfc4122() ?? ''] ?? [],
            ),
            $activities,
        );
    }

    /**
     * @param list<Enrollment> $enrollments входящие заявки в кружок активности
     */
    public function create(Activity $activity, array $enrollments = []): ActivityResponse
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
            enrollments: array_map($this->incoming(...), $enrollments),
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
            days: array_column($branch->getDays(), 'value'),
            timeFrom: $first?->getTimeFromString(),
            timeTo: $first?->getTimeToString(),
            slots: array_map(ScheduleSlotView::fromEntity(...), $branch->getSlots()),
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
