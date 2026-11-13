<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Dto\Activity\ActivityResponse;
use App\Dto\Admin\ActivityView;
use App\Dto\Admin\ClubRefView;
use App\Dto\Admin\EnrollmentView;
use App\Dto\Admin\UserRefView;
use App\Dto\Enrollment\EnrollmentResponse;
use App\Entity\Activity;
use App\Entity\Club;
use App\Entity\Enrollment;
use App\Service\Activity\ActivityResponseFactory;
use App\Service\Formatter\ScheduleFormatter;

final readonly class AdminViewFactory
{
    public function __construct(
        private ActivityResponseFactory $activityResponses,
        private ScheduleFormatter $scheduleFormatter,
    ) {
    }

    /**
     * @param list<Activity> $activities
     *
     * @return list<ActivityView>
     */
    public function activities(array $activities): array
    {
        return array_map(
            static fn (Activity $activity, ActivityResponse $response): ActivityView => new ActivityView(
                $response,
                UserRefView::fromUser($activity->getOwner()),
                $activity->getClub()?->isPublished(),
            ),
            $activities,
            $this->activityResponses->createList($activities),
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
        return array_map(
            fn (Enrollment $enrollment): EnrollmentView => new EnrollmentView(
                EnrollmentResponse::fromEntity($enrollment, $this->scheduleFormatter),
                self::clubRef($enrollment->getClub()),
                UserRefView::fromUser($enrollment->getUser()),
            ),
            $enrollments,
        );
    }

    private static function clubRef(Club $club): ClubRefView
    {
        $owner = $club->getOwner();

        return new ClubRefView(
            id: $club->getSlug(),
            name: $club->getName(),
            isPublished: $club->isPublished(),
            owner: null === $owner ? null : UserRefView::fromUser($owner),
        );
    }
}
