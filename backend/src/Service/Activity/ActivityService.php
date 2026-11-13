<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Dto\Activity\ActivityBranchInput;
use App\Dto\Activity\ActivityResponse;
use App\Dto\Activity\CreateActivityRequest;
use App\Dto\Activity\ScheduleSlotInput;
use App\Entity\Activity;
use App\Entity\ActivityBranch;
use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Entity\ScheduleSlot;
use App\Entity\User;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ActivityService
{
    public function __construct(
        private ActivityRepository $activities,
        private EntityManagerInterface $entityManager,
        private ActivityResponseFactory $responses,
    ) {
    }

    /**
     * @return list<ActivityResponse>
     */
    public function listFor(User $owner): array
    {
        return $this->responses->createList($this->activities->findByOwner($owner));
    }

    /**
     * Чужая активность неотличима от несуществующей.
     */
    public function getFor(string $id, User $owner): ?ActivityResponse
    {
        $activity = $this->activities->findOneOwnedBy($id, $owner);

        return null === $activity ? null : $this->responses->createList([$activity])[0];
    }

    public function create(CreateActivityRequest $request, User $owner): ActivityResponse
    {
        $priceType = PriceType::from((string) $request->priceType);

        $activity = new Activity(
            owner: $owner,
            name: trim((string) $request->name),
            category: Category::from((string) $request->category),
            description: trim((string) $request->description),
            ageMin: (int) $request->ageMin,
            ageMax: (int) $request->ageMax,
            visitType: VisitType::from((string) $request->visitType),
            priceType: $priceType,
            priceAmount: PriceType::Free === $priceType ? 0 : (int) $request->priceAmount,
            tags: self::tags($request->tags ?? []),
            emoji: null === $request->emoji ? null : trim($request->emoji),
        );

        foreach ($request->branches ?? [] as $branchInput) {
            $activity->addBranch($this->branch($branchInput));
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->responses->create($activity);
    }

    private function branch(ActivityBranchInput $input): ActivityBranch
    {
        $phone = null === $input->phone ? null : trim($input->phone);
        $branch = new ActivityBranch(trim((string) $input->name), trim((string) $input->address), '' === $phone ? null : $phone);

        // Одинаковые слоты (день + время) схлопываются
        $seen = [];
        foreach ($input->slots ?? [] as $slotInput) {
            \assert($slotInput instanceof ScheduleSlotInput);
            $slot = new ScheduleSlot(DayOfWeek::from((string) $slotInput->day), (string) $slotInput->timeFrom, (string) $slotInput->timeTo);
            if (isset($seen[$slot->sortKey()])) {
                continue;
            }
            $seen[$slot->sortKey()] = true;
            $branch->addSlot($slot);
        }

        return $branch;
    }

    /**
     * @param list<string> $tags
     *
     * @return list<string>
     */
    private static function tags(array $tags): array
    {
        return array_values(array_unique(array_map(static fn (string $tag): string => trim($tag), $tags)));
    }
}
