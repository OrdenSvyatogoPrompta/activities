<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Dto\Admin\ActivityBranchView;
use App\Dto\Admin\ActivityEnrollmentView;
use App\Dto\Admin\ActivityView;
use App\Dto\Admin\ChildView;
use App\Dto\Admin\ClubRefView;
use App\Dto\Admin\EnrollmentView;
use App\Dto\Admin\SlotView;
use App\Dto\Admin\UserRefView;
use App\Entity\Activity;
use App\Entity\ActivityBranch;
use App\Entity\Club;
use App\Entity\Enrollment;
use App\Entity\Enum\DayOfWeek;
use App\Repository\EnrollmentRepository;
use App\Service\Formatter\ScheduleFormatter;

/**
 * Собирает ответы админских эндпоинтов из сущностей.
 */
final readonly class AdminViewFactory
{
    public const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private EnrollmentRepository $enrollmentRepository,
        private ScheduleFormatter $scheduleFormatter,
    ) {
    }

    /**
     * Входящие заявки всех одобренных активностей загружаются одним запросом.
     *
     * @param list<Activity> $activities
     *
     * @return list<ActivityView>
     */
    public function activities(array $activities): array
    {
        $clubs = [];
        foreach ($activities as $activity) {
            $club = $activity->getClub();
            if (null !== $club) {
                $clubs[$club->getId()->toRfc4122()] = $club;
            }
        }

        /** @var array<string, list<Enrollment>> $enrollmentsByClub */
        $enrollmentsByClub = [];
        foreach ($this->enrollmentRepository->findIncomingForClubs(array_values($clubs)) as $enrollment) {
            $enrollmentsByClub[$enrollment->getClub()->getId()->toRfc4122()][] = $enrollment;
        }

        return array_map(
            fn (Activity $activity): ActivityView => $this->buildActivity(
                $activity,
                $enrollmentsByClub[$activity->getClub()?->getId()->toRfc4122() ?? ''] ?? [],
            ),
            $activities,
        );
    }

    public function activity(Activity $activity): ActivityView
    {
        return $this->activities([$activity])[0];
    }

    /**
     * @param list<Enrollment> $enrollments
     *
     * @return list<EnrollmentView>
     */
    public function enrollments(array $enrollments): array
    {
        return array_map($this->buildEnrollment(...), $enrollments);
    }

    /**
     * @param list<Enrollment> $incoming заявки в кружок активности (без cancelled)
     */
    private function buildActivity(Activity $activity, array $incoming): ActivityView
    {
        return new ActivityView(
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
            visitType: $activity->getVisitType()->value,
            priceType: $activity->getPriceType()->value,
            priceAmount: $activity->getPriceAmount(),
            tags: $activity->getTags(),
            branches: array_values(array_map($this->buildActivityBranch(...), $activity->getBranches()->toArray())),
            enrollments: array_map($this->buildActivityEnrollment(...), $incoming),
            owner: UserRefView::fromUser($activity->getOwner()),
        );
    }

    private function buildActivityBranch(ActivityBranch $branch): ActivityBranchView
    {
        $first = $branch->getFirstSlot();

        return new ActivityBranchView(
            id: $branch->getId()->toRfc4122(),
            name: $branch->getName(),
            address: $branch->getAddress(),
            phone: $branch->getPhone(),
            days: self::dayValues($branch->getDays()),
            timeFrom: $first?->getTimeFromString(),
            timeTo: $first?->getTimeToString(),
            slots: array_map(SlotView::fromSlot(...), $branch->getSlots()),
        );
    }

    private function buildActivityEnrollment(Enrollment $enrollment): ActivityEnrollmentView
    {
        return new ActivityEnrollmentView(
            id: $enrollment->getId()->toRfc4122(),
            personName: $enrollment->getUser()->getName(),
            childName: $enrollment->getChild()?->getFirstName(),
            branchId: $enrollment->getBranch()?->getId()->toRfc4122(),
            branchName: $enrollment->getBranch()?->getName(),
            enrolledAt: $enrollment->getCreatedAt()->format(self::DATE_FORMAT),
            status: $enrollment->getStatus()->value,
        );
    }

    private function buildEnrollment(Enrollment $enrollment): EnrollmentView
    {
        $club = $enrollment->getClub();
        $branch = $enrollment->getBranch();
        $child = $enrollment->getChild();

        return new EnrollmentView(
            id: $enrollment->getId()->toRfc4122(),
            clubId: $club->getSlug(),
            clubName: $club->getName(),
            branchId: $branch?->getId()->toRfc4122(),
            branchName: $branch?->getName(),
            schedule: null !== $branch ? $this->scheduleFormatter->formatBranch($branch) : null,
            days: self::dayValues(null !== $branch ? $branch->getDays() : $club->getDays()),
            color: $club->getColor(),
            forChild: null !== $child ? ChildView::fromChild($child) : null,
            enrolledAt: $enrollment->getCreatedAt()->format(self::DATE_FORMAT),
            status: $enrollment->getStatus()->value,
            rejectReason: $enrollment->getRejectReason(),
            club: $this->buildClubRef($club),
            user: UserRefView::fromUser($enrollment->getUser()),
        );
    }

    private function buildClubRef(Club $club): ClubRefView
    {
        $owner = $club->getOwner();

        return new ClubRefView(
            id: $club->getSlug(),
            name: $club->getName(),
            isPublished: $club->isPublished(),
            owner: null !== $owner ? UserRefView::fromUser($owner) : null,
        );
    }

    /**
     * @param list<DayOfWeek> $days
     *
     * @return list<string>
     */
    private static function dayValues(array $days): array
    {
        return array_map(static fn (DayOfWeek $day): string => $day->value, $days);
    }
}
