<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Порядок объявления — Пн…Вс, на нём держится сортировка.
 */
enum DayOfWeek: string
{
    use EnumValues;

    case Monday = 'Пн';
    case Tuesday = 'Вт';
    case Wednesday = 'Ср';
    case Thursday = 'Чт';
    case Friday = 'Пт';
    case Saturday = 'Сб';
    case Sunday = 'Вс';

    /**
     * 1 (Пн) … 7 (Вс), как `date('N')`.
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
     * «Пн, Ср, Пт» → [Monday, Wednesday, Friday].
     *
     * @return list<self>
     */
    public static function parseList(string $days): array
    {
        return array_map(static fn (string $day): self => self::from(trim($day)), explode(',', $days));
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
