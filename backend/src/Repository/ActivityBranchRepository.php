<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityBranch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityBranch>
 */
class ActivityBranchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityBranch::class);
    }
}
