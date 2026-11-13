<?php

declare(strict_types=1);

namespace App\Service\Formatter;

use App\Entity\Enum\PriceType;

/**
 * Поле `price` для API: `Бесплатно` или `2 500 ₽ / мес` (разряды через обычный пробел, как в моках).
 */
final class PriceFormatter
{
    public const string FREE = 'Бесплатно';

    public function format(PriceType $priceType, int $priceAmount): string
    {
        if (PriceType::Free === $priceType) {
            return self::FREE;
        }

        return number_format($priceAmount, 0, '', ' ').' ₽ / мес';
    }
}
