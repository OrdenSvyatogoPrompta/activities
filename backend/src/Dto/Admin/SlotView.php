<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\ScheduleSlot;

/**
 * Слот расписания: `{ day, timeFrom, timeTo }`, время `HH:MM`.
 */
final readonly class SlotView
{
    public function __construct(
        public string $day,
        public string $timeFrom,
        public string $timeTo,
    ) {
    }

    public static function fromSlot(ScheduleSlot $slot): self
    {
        return new self($slot->getDay()->value, $slot->getTimeFromString(), $slot->getTimeToString());
    }
}
