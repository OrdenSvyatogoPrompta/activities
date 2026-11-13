<?php

declare(strict_types=1);

namespace App\Service\Formatter;

use App\Entity\ActivityBranch;
use App\Entity\Branch;
use App\Entity\Enum\DayOfWeek;
use App\Entity\ScheduleSlot;

/**
 * Поле `schedule` для API.
 *
 * Слоты с одинаковым временем группируются: `Пн, Ср, Пт · 15:00–18:00`; группы — через `\n`,
 * в порядке первого дня группы (затем по времени). Если у филиала задан scheduleNote
 * (например, «По договорённости»), возвращается он.
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
            static fn (array $group): string => implode(', ', array_map(
                static fn (DayOfWeek $day): string => $day->value,
                DayOfWeek::sorted($group['days']),
            )).' · '.$group['time'],
            array_values($groups),
        ));
    }

    /**
     * Дни недели слотов, уникальные, Пн…Вс.
     *
     * @param iterable<ScheduleSlot> $slots
     *
     * @return list<DayOfWeek>
     */
    public function days(iterable $slots): array
    {
        $days = [];
        foreach ($slots as $slot) {
            $days[] = $slot->getDay();
        }

        return DayOfWeek::sorted($days);
    }
}
