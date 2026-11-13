<?php

declare(strict_types=1);

namespace App\Service\Formatter;

use App\Entity\ActivityBranch;
use App\Entity\Branch;
use App\Entity\Enum\DayOfWeek;
use App\Entity\ScheduleSlot;

/**
 * Слоты с одинаковым временем группируются: `Пн, Ср, Пт · 15:00–18:00`, группы — через `\n`.
 */
final class ScheduleFormatter
{
    public function formatBranch(Branch|ActivityBranch $branch): string
    {
        if ($branch instanceof Branch && null !== $branch->getScheduleNote() && '' !== $branch->getScheduleNote()) {
            return $branch->getScheduleNote();
        }

        return $this->formatSlots($branch->getSlots());
    }

    /**
     * @param iterable<ScheduleSlot> $slots
     */
    public function formatSlots(iterable $slots): string
    {
        /** @var array<string, array{days: list<DayOfWeek>, time: string}> $groups */
        $groups = [];
        foreach (ScheduleSlot::sort($slots) as $slot) {
            $time = $slot->getTimeFromString().'–'.$slot->getTimeToString();
            $groups[$time] ??= ['days' => [], 'time' => $time];
            $groups[$time]['days'][] = $slot->getDay();
        }

        // Слоты отсортированы, поэтому группы уже идут в порядке первого дня и времени
        return implode("\n", array_map(
            static fn (array $group): string => implode(', ', array_column(DayOfWeek::sorted($group['days']), 'value')).' · '.$group['time'],
            array_values($groups),
        ));
    }
}
