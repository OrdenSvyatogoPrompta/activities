<?php

declare(strict_types=1);

namespace App\Dto\Activity;

use App\Dto\Catalog\ScheduleSlotView;

final readonly class ActivityBranchResponse
{
    /**
     * @param list<ScheduleSlotView> $slots
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public ?string $phone,
        public string $schedule,
        public array $slots,
    ) {
    }
}
