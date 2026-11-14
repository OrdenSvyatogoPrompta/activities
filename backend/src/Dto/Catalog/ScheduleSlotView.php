<?php

declare(strict_types=1);

namespace App\Dto\Catalog;

use App\Entity\ScheduleSlot;

final readonly class ScheduleSlotView
{
    public function __construct(
        public string $day,
        public string $timeFrom,
        public string $timeTo,
    ) {
    }

    public static function fromEntity(ScheduleSlot $slot): self
    {
        return new self($slot->getDay()->value, $slot->getTimeFromString(), $slot->getTimeToString());
    }
}
