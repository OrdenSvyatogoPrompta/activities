<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Club;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Club>
 */
class ClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Club::class);
    }

    /**
     * Весь опубликованный каталог по имени, с филиалами и слотами одним запросом.
     *
     * @return list<Club>
     */
    public function findPublished(): array
    {
        return $this->createWithBranchesQueryBuilder()
            ->andWhere('c.isPublished = true')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('b.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Опубликованный кружок по slug (неопубликованный → null → 404).
     */
    public function findOnePublishedBySlug(string $slug): ?Club
    {
        $club = $this->findOneBySlugWithBranches($slug);

        return null !== $club && $club->isPublished() ? $club : null;
    }

    /**
     * Кружок по slug независимо от публикации (модерация, админка).
     */
    public function findOneBySlugWithBranches(string $slug): ?Club
    {
        return $this->createWithBranchesQueryBuilder()
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->addOrderBy('b.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function slugExists(string $slug): bool
    {
        return $this->count(['slug' => $slug]) > 0;
    }

    /**
     * Кружки, которыми владеет пользователь.
     *
     * @return list<Club>
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $owner->getId(), UuidType::NAME)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function createWithBranchesQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('b', 's')
            ->leftJoin('c.branches', 'b')
            ->leftJoin('b.slots', 's');
    }
}
