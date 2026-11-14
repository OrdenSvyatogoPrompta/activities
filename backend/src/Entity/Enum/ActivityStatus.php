<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ActivityStatus: string
{
    use EnumValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
