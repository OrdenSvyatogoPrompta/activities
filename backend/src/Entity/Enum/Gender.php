<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum Gender: string
{
    use EnumValues;

    case Male = 'male';
    case Female = 'female';
}
