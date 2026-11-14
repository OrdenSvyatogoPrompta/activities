<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

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
     * @return list<Club>
     */
    public function findPublished(): array
    {
        return $this->createWithBranchesQueryBuilder()
            ->andWhere('c.isPublished = true')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('c.slug', 'ASC')
            ->addOrderBy('b.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOnePublishedBySlug(string $slug): ?Club
    {
        $club = $this->findOneBySlugWithBranches($slug);

        return null !== $club && $club->isPublished() ? $club : null;
    }

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

    private function createWithBranchesQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('b', 's')
            ->leftJoin('c.branches', 'b')
            ->leftJoin('b.slots', 's');
    }
}
