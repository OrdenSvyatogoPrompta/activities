<?php

declare(strict_types=1);

namespace App\Dto\Activity;

use App\Dto\Catalog\ScheduleSlotView;

/**
 * `days/timeFrom/timeTo` — упрощённое расписание для фронта: дни всех слотов и время первого.
 */
final readonly class ActivityBranchResponse
{
    /**
     * @param list<string>           $days
     * @param list<ScheduleSlotView> $slots
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public ?string $phone,
        public string $schedule,
        public array $days,
        public ?string $timeFrom,
        public ?string $timeTo,
        public array $slots,
    ) {
    }
}
