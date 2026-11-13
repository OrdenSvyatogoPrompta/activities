<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * День недели. Значения совпадают с `DayOfWeek` фронтенда, порядок объявления — Пн…Вс.
 */
enum DayOfWeek: string
{
    case Monday = 'Пн';
    case Tuesday = 'Вт';
    case Wednesday = 'Ср';
    case Thursday = 'Чт';
    case Friday = 'Пт';
    case Saturday = 'Сб';
    case Sunday = 'Вс';

    /**
     * Номер дня, 1 (Пн) … 7 (Вс), как ISO-8601 (`date('N')`).
     */
    public function isoNumber(): int
    {
        return match ($this) {
            self::Monday => 1,
            self::Tuesday => 2,
            self::Wednesday => 3,
            self::Thursday => 4,
            self::Friday => 5,
            self::Saturday => 6,
            self::Sunday => 7,
        };
    }

    /**
     * Уникальные дни в порядке Пн…Вс.
     *
     * @param iterable<self> $days
     *
     * @return list<self>
     */
    public static function sorted(iterable $days): array
    {
        $present = [];
        foreach ($days as $day) {
            $present[$day->value] = true;
        }

        return array_values(array_filter(self::cases(), static fn (self $day): bool => isset($present[$day->value])));
    }
}
