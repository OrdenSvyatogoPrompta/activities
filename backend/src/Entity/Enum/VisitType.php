<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum VisitType: string
{
    case Recurring = 'recurring';
    case OneTime = 'oneTime';
    case Both = 'both';
}
