<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Activity;
use App\Entity\Branch;
use App\Entity\Club;
use App\Entity\User;
use App\Repository\ClubRepository;
use App\Service\SlugGenerator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Модерация активностей: одобрение (публикация кружка в каталоге) и отклонение.
 *
 * Оба действия выполняются в транзакции с блокировкой строки активности
 * (SELECT … FOR UPDATE): два администратора, одобряющие одну активность одновременно,
 * не создадут два кружка — второй получит 409.
 */
final readonly class ActivityModerator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClubRepository $clubRepository,
        private SlugGenerator $slugGenerator,
        private ShortDescriptionExtractor $shortDescriptionExtractor,
    ) {
    }

    /**
     * Создаёт из активности опубликованный кружок (владелец — автор активности),
     * копирует филиалы со слотами и связывает кружок с активностью.
     *
     * @throws ModerationConflictException активность не pending или без филиалов (409)
     */
    public function approve(Activity $activity, User $moderator): Club
    {
        return $this->entityManager->wrapInTransaction(function () use ($activity, $moderator): Club {
            $this->lockPending($activity);
            if ($activity->getBranches()->isEmpty()) {
                throw ModerationConflictException::noBranches();
            }

            $description = trim($activity->getDescription());
            $club = new Club(
                slug: $this->slugGenerator->generate($activity->getName(), $this->clubRepository->slugExists(...)),
                name: $activity->getName(),
                categories: [$activity->getCategory()],
                emoji: $activity->getEmoji(),
                color: $activity->getCategory()->color(),
                shortDescription: $this->shortDescriptionExtractor->extract($description),
                fullDescription: $description,
                ageMin: $activity->getAgeMin(),
                ageMax: $activity->getAgeMax(),
                visitType: $activity->getVisitType(),
                priceType: $activity->getPriceType(),
                priceAmount: $activity->getPriceAmount(),
                tags: $activity->getTags(),
                owner: $activity->getOwner(),
                sourceActivity: $activity,
                isPublished: true,
            );
            foreach ($activity->getBranches() as $activityBranch) {
                $club->addBranch(Branch::fromActivityBranch($activityBranch));
            }

            $activity->approve($club, $moderator);
            $this->entityManager->persist($club);

            return $club;
        });
    }

    /**
     * @param string $comment причина отклонения (непустая — проверяет DTO запроса)
     *
     * @throws ModerationConflictException активность не pending (409)
     */
    public function reject(Activity $activity, string $comment, User $moderator): void
    {
        $this->entityManager->wrapInTransaction(function () use ($activity, $comment, $moderator): void {
            $this->lockPending($activity);
            $activity->reject($comment, $moderator);
        });
    }

    /**
     * Перечитывает активность под блокировкой и проверяет, что она всё ещё на рассмотрении.
     */
    private function lockPending(Activity $activity): void
    {
        $this->entityManager->refresh($activity, LockMode::PESSIMISTIC_WRITE);
        if (!$activity->isPending()) {
            throw ModerationConflictException::notPending();
        }
    }
}
