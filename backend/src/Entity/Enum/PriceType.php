<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PriceType: string
{
    use EnumValues;

    case Free = 'free';
    case Pushkin = 'pushkin';
    case Paid = 'paid';
}
