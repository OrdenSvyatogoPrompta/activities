<?php

declare(strict_types=1);

namespace App\Dto\Catalog;

/**
 * Филиал кружка в API (`Branch` из models/club.model.ts + `slots`).
 */
final readonly class BranchView
{
    /**
     * @param list<string>           $days  дни занятий, Пн…Вс
     * @param list<ScheduleSlotView> $slots слоты по дню недели и времени
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public string $phone,
        public string $schedule,
        public array $days,
        public array $slots,
    ) {
    }
}
