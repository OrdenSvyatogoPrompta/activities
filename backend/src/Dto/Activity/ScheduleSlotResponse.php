<?php

declare(strict_types=1);

namespace App\Dto\Activity;

/**
 * Слот расписания в ответе API: `{ day, timeFrom, timeTo }`.
 */
final readonly class ScheduleSlotResponse
{
    public function __construct(
        public string $day,
        public string $timeFrom,
        public string $timeTo,
    ) {
    }
}
