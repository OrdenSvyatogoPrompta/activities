<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Child;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Child>
 */
class ChildRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Child::class);
    }

    /**
     * @return list<Child>
     */
    public function findByParent(User $parent): array
    {
        return $this->findBy(['parent' => $parent], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    public function findOneOwnedBy(Uuid|string $id, User $parent): ?Child
    {
        $uuid = Uuids::parse($id);

        return null === $uuid ? null : $this->findOneBy(['id' => $uuid, 'parent' => $parent]);
    }

    public function countByParent(User $parent): int
    {
        return $this->count(['parent' => $parent]);
    }
}
