<?php

declare(strict_types=1);

namespace App\Service\Formatter;

use App\Entity\Enum\PriceType;

/**
 * `Бесплатно` или `2 500 ₽ / мес`.
 */
final class PriceFormatter
{
    public function format(PriceType $priceType, int $priceAmount): string
    {
        if (PriceType::Free === $priceType) {
            return 'Бесплатно';
        }

        return number_format($priceAmount, 0, '', ' ').' ₽ / мес';
    }
}
