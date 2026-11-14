<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Dto\Activity\ActivityResponse;
use App\Dto\Admin\ActivityView;
use App\Dto\Admin\UserRefView;
use App\Entity\Activity;
use App\Service\Activity\ActivityResponseFactory;

final readonly class AdminViewFactory
{
    public function __construct(
        private ActivityResponseFactory $activityResponses,
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
}
