<?php

declare(strict_types=1);

namespace App\Dto\Activity;

/**
 * Филиал активности в ответе API (совместим с `ActivityBranch` фронтенда).
 *
 * `days/timeFrom/timeTo` — для совместимости: дни всех слотов и время первого слота;
 * полное расписание — в `slots` и строкой в `schedule`.
 */
final readonly class ActivityBranchResponse
{
    /**
     * @param list<string>               $days
     * @param list<ScheduleSlotResponse> $slots
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
