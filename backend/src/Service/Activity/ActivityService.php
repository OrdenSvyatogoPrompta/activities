<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Dto\Activity\ActivityBranchInput;
use App\Dto\Activity\ActivityResponse;
use App\Dto\Activity\CreateActivityRequest;
use App\Dto\Activity\ScheduleSlotInput;
use App\Entity\Activity;
use App\Entity\ActivityBranch;
use App\Entity\Club;
use App\Entity\Enrollment;
use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Entity\ScheduleSlot;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Активности организатора в ЛК: список, просмотр, создание (статус pending — на модерацию).
 */
final readonly class ActivityService
{
    public function __construct(
        private ActivityRepository $activities,
        private EnrollmentRepository $enrollments,
        private EntityManagerInterface $entityManager,
        private ActivityResponseFactory $responses,
    ) {
    }

    /**
     * Свои активности всех статусов, новые первыми, с входящими заявками.
     *
     * @return list<ActivityResponse>
     */
    public function listFor(User $owner): array
    {
        $activities = $this->activities->findByOwner($owner);
        $incoming = $this->incomingByClub($activities);

        return array_map(
            fn (Activity $activity): ActivityResponse => $this->responses->activity(
                $activity,
                $incoming[self::clubKey($activity->getClub())] ?? [],
            ),
            $activities,
        );
    }

    /**
     * Своя активность по id; чужая или несуществующая — null (→ 404).
     */
    public function getFor(string $id, User $owner): ?ActivityResponse
    {
        $activity = $this->activities->findOneOwnedBy($id, $owner);
        if (null === $activity) {
            return null;
        }

        return $this->responses->activity($activity, $this->incomingByClub([$activity])[self::clubKey($activity->getClub())] ?? []);
    }

    /**
     * Создаёт активность на модерации. Тело уже провалидировано (#[MapRequestPayload]).
     */
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
            // Для free сущность сама сохраняет 0
            priceAmount: PriceType::Free === $priceType ? 0 : (int) $request->priceAmount,
            tags: self::tags($request->tags ?? []),
            emoji: null === $request->emoji ? null : trim($request->emoji),
        );

        foreach ($request->branches ?? [] as $branchInput) {
            $activity->addBranch($this->branch($branchInput));
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->responses->activity($activity);
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
     * Входящие заявки (без cancelled) в кружки одобренных активностей одним запросом.
     *
     * @param list<Activity> $activities
     *
     * @return array<string, list<Enrollment>> по ключу clubKey()
     */
    private function incomingByClub(array $activities): array
    {
        $clubs = [];
        foreach ($activities as $activity) {
            $club = $activity->getClub();
            if (null !== $club) {
                $clubs[self::clubKey($club)] = $club;
            }
        }

        $grouped = [];
        foreach ($this->enrollments->findIncomingForClubs(array_values($clubs)) as $enrollment) {
            $grouped[self::clubKey($enrollment->getClub())][] = $enrollment;
        }

        return $grouped;
    }

    private static function clubKey(?Club $club): string
    {
        return null === $club ? '' : $club->getId()->toRfc4122();
    }

    /**
     * Теги без пробелов по краям и без повторов, в исходном порядке.
     *
     * @param list<string> $tags
     *
     * @return list<string>
     */
    private static function tags(array $tags): array
    {
        return array_values(array_unique(array_map(static fn (string $tag): string => trim($tag), $tags)));
    }
}
