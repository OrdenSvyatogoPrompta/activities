<?php

declare(strict_types=1);

namespace App\Service\Formatter;

/**
 * Поле `age` для API: `6–18 лет` (длинное тире), склонение «год/года/лет» по последнему числу.
 */
final class AgeFormatter
{
    public function format(int $ageMin, int $ageMax): string
    {
        $range = $ageMin === $ageMax ? (string) $ageMax : $ageMin.'–'.$ageMax;

        return $range.' '.self::years($ageMax);
    }

    /**
     * «год», «года» или «лет» для числа: 1, 21 → год; 2–4, 22 → года; 0, 5–20, 111 → лет.
     */
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
