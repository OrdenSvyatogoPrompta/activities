<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PriceType: string
{
    case Free = 'free';
    case Pushkin = 'pushkin';
    case Paid = 'paid';
}
