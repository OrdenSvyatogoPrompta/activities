<?php

declare(strict_types=1);

namespace App\Entity\Enum;

trait EnumValues
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
