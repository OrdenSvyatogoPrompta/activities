<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ScheduleSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScheduleSlot>
 */
class ScheduleSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduleSlot::class);
    }
}
