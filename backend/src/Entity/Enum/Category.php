<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum Category: string
{
    use EnumValues;

    case Sport = 'Спорт';
    case Creativity = 'Творчество';
    case Science = 'Наука';
    case Music = 'Музыка';
    case Dance = 'Танцы';

    /**
     * Цвет кружка, созданного из одобренной активности.
     */
    public function color(): string
    {
        return match ($this) {
            self::Sport => '#0ea5e9',
            self::Creativity => '#f59e0b',
            self::Science => '#6366f1',
            self::Music => '#ec4899',
            self::Dance => '#8b5cf6',
        };
    }
}
