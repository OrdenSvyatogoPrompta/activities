<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Enum\ActivityStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * @return list<Activity>
     */
    public function findByOwner(User $owner): array
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('a.owner = :owner')
            ->setParameter('owner', $owner->getId(), UuidType::NAME)
            ->addOrderBy('a.createdAt', 'DESC')
            ->addOrderBy('ab.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneOwnedBy(Uuid|string $id, User $owner): ?Activity
    {
        $uuid = Uuids::parse($id);
        if (null === $uuid) {
            return null;
        }

        return $this->createDetailedQueryBuilder()
            ->andWhere('a.id = :id')
            ->andWhere('a.owner = :owner')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('owner', $owner->getId(), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneWithDetails(Uuid|string $id): ?Activity
    {
        $uuid = Uuids::parse($id);
        if (null === $uuid) {
            return null;
        }

        return $this->createDetailedQueryBuilder()
            ->andWhere('a.id = :id')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Activity>
     */
    public function findByStatus(ActivityStatus $status): array
    {
        return $this->createDetailedQueryBuilder()
            ->andWhere('a.status = :status')
            ->setParameter('status', $status->value)
            ->addOrderBy('a.createdAt', 'ASC')
            ->addOrderBy('ab.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByOwner(User $owner): int
    {
        return $this->count(['owner' => $owner]);
    }

    private function createDetailedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->addSelect('o', 'ab', 's', 'c')
            ->innerJoin('a.owner', 'o')
            ->leftJoin('a.branches', 'ab')
            ->leftJoin('ab.slots', 's')
            ->leftJoin('a.club', 'c');
    }
}
