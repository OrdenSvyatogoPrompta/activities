<?php

declare(strict_types=1);

namespace App\Dto\Catalog;

/**
 * Слот расписания филиала в API: день недели и время `HH:MM`.
 */
final readonly class ScheduleSlotView
{
    public function __construct(
        public string $day,
        public string $timeFrom,
        public string $timeTo,
    ) {
    }
}
