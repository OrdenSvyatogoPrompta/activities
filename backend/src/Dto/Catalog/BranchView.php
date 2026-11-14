<?php

declare(strict_types=1);

namespace App\Dto\Catalog;

final readonly class BranchView
{
    /**
     * @param list<string>           $days
     * @param list<ScheduleSlotView> $slots
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
