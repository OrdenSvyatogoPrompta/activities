<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Formatter;

use App\Service\Formatter\AgeFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AgeFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function ranges(): iterable
    {
        yield 'как в моках, 18' => [6, 18, '6–18 лет'];
        yield 'как в моках, 12' => [4, 12, '4–12 лет'];
        yield 'как в моках, 99' => [7, 99, '7–99 лет'];
        yield 'по последнему числу: 21 год' => [5, 21, '5–21 год'];
        yield 'по последнему числу: 2 года' => [1, 2, '1–2 года'];
        yield 'по последнему числу: 14 лет' => [10, 14, '10–14 лет'];
        yield 'одно число' => [5, 5, '5 лет'];
        yield 'один год' => [1, 1, '1 год'];
    }

    #[DataProvider('ranges')]
    public function testFormat(int $min, int $max, string $expected): void
    {
        self::assertSame($expected, (new AgeFormatter())->format($min, $max));
    }

    /**
     * @return iterable<array{int, string}>
     */
    public static function years(): iterable
    {
        foreach ([1 => 'год', 2 => 'года', 3 => 'года', 4 => 'года', 5 => 'лет', 0 => 'лет', 10 => 'лет',
            11 => 'лет', 12 => 'лет', 13 => 'лет', 14 => 'лет', 15 => 'лет', 20 => 'лет', 21 => 'год',
            22 => 'года', 24 => 'года', 25 => 'лет', 99 => 'лет', 101 => 'год', 111 => 'лет', 112 => 'лет'] as $number => $word) {
            yield (string) $number => [$number, $word];
        }
    }

    #[DataProvider('years')]
    public function testYearsDeclension(int $number, string $expected): void
    {
        self::assertSame($expected, AgeFormatter::years($number));
    }
}
