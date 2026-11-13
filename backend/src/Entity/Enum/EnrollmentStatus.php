<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Значения совпадают с местами workflow `enrollment`; статус меняется только через него.
 */
enum EnrollmentStatus: string
{
    use EnumValues;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Активная заявка занимает место участника: дубль подать нельзя, ребёнка удалить нельзя.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }
}
