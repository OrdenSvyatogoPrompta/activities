<?php

declare(strict_types=1);

namespace App\Service\Formatter;

/**
 * `6–18 лет`: склонение по последнему числу.
 */
final class AgeFormatter
{
    public function format(int $ageMin, int $ageMax): string
    {
        $range = $ageMin === $ageMax ? (string) $ageMax : $ageMin.'–'.$ageMax;

        return $range.' '.self::years($ageMax);
    }

    public static function years(int $number): string
    {
        $mod100 = abs($number) % 100;
        $mod10 = $mod100 % 10;

        return match (true) {
            $mod100 >= 11 && $mod100 <= 14 => 'лет',
            1 === $mod10 => 'год',
            $mod10 >= 2 && $mod10 <= 4 => 'года',
            default => 'лет',
        };
    }
}
