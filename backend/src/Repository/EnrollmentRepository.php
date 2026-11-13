<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Child;
use App\Entity\Club;
use App\Entity\Enrollment;
use App\Entity\Enum\EnrollmentStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Enrollment>
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    /**
     * Активная (pending|confirmed) заявка участника в кружок: пользователь «для себя»
     * (child = null) или конкретный ребёнок. Есть — значит новая заявка даёт 409.
     */
    public function findActiveForParticipant(User $user, Club $club, ?Child $child): ?Enrollment
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.club = :club')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->setParameter('club', $club->getId(), UuidType::NAME)
            ->setParameter('statuses', self::values(EnrollmentStatus::active()), ArrayParameterType::STRING)
            ->setMaxResults(1);

        if (null === $child) {
            $qb->andWhere('e.child IS NULL');
        } else {
            $qb->andWhere('e.child = :child')->setParameter('child', $child->getId(), UuidType::NAME);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Есть ли у ребёнка активные заявки (тогда удалять его нельзя → 409).
     */
    public function hasActiveForChild(Child $child): bool
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.child = :child')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('child', $child->getId(), UuidType::NAME)
            ->setParameter('statuses', self::values(EnrollmentStatus::active()), ArrayParameterType::STRING)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Число активных заявок пользователя (счётчик `stats.enrollments`).
     */
    public function countActiveByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.user = :user')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->setParameter('statuses', self::values(EnrollmentStatus::active()), ArrayParameterType::STRING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Заявки пользователя, новые первыми, с кружком, филиалом, слотами и ребёнком.
     *
     * @param list<EnrollmentStatus>|null $statuses null — все, кроме cancelled
     *
     * @return list<Enrollment>
     */
    public function findByUser(User $user, ?array $statuses = null): array
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('e.user = :user')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('statuses', self::values($statuses ?? self::notCancelled()), ArrayParameterType::STRING)
            ->addOrderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Заявка по id со связями (для cancel/confirm/reject); некорректный id → null.
     */
    public function findOneWithDetails(Uuid|string $id): ?Enrollment
    {
        $uuid = Uuids::parse($id);
        if (null === $uuid) {
            return null;
        }

        return $this->createDetailedQueryBuilder()
            ->andWhere('e.id = :id')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Входящие заявки в кружки (для активностей организатора), кроме cancelled,
     * по дате подачи. Один запрос на все кружки — без N+1.
     *
     * @param list<Club> $clubs
     *
     * @return list<Enrollment>
     */
    public function findIncomingForClubs(array $clubs): array
    {
        if ([] === $clubs) {
            return [];
        }

        return $this->createDetailedQueryBuilder()
            ->andWhere('e.club IN (:clubs)')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('clubs', array_map(static fn (Club $club): string => $club->getId()->toBinary(), $clubs), ArrayParameterType::BINARY)
            ->setParameter('statuses', self::values(self::notCancelled()), ArrayParameterType::STRING)
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Список заявок для администратора, старые первыми.
     *
     * @param bool $ownerlessOnly только заявки в кружки без владельца (их подтверждает админ)
     *
     * @return list<Enrollment>
     */
    public function findForAdmin(?EnrollmentStatus $status = EnrollmentStatus::Pending, bool $ownerlessOnly = false): array
    {
        $qb = $this->createDetailedQueryBuilder()
            ->addOrderBy('e.createdAt', 'ASC');

        if (null !== $status) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status->value);
        }
        if ($ownerlessOnly) {
            $qb->andWhere('c.owner IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Заявка + автор, кружок, филиал со слотами, ребёнок — всё, что нужно для ответа API.
     */
    private function createDetailedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->addSelect('u', 'c', 'b', 's', 'ch')
            ->innerJoin('e.user', 'u')
            ->innerJoin('e.club', 'c')
            ->leftJoin('e.branch', 'b')
            ->leftJoin('b.slots', 's')
            ->leftJoin('e.child', 'ch');
    }

    /**
     * @return list<EnrollmentStatus>
     */
    private static function notCancelled(): array
    {
        return array_values(array_filter(
            EnrollmentStatus::cases(),
            static fn (EnrollmentStatus $status): bool => EnrollmentStatus::Cancelled !== $status,
        ));
    }

    /**
     * @param list<EnrollmentStatus> $statuses
     *
     * @return list<string>
     */
    private static function values(array $statuses): array
    {
        return array_map(static fn (EnrollmentStatus $status): string => $status->value, $statuses);
    }
}
