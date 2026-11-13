<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Formatter;

use App\Entity\Enum\PriceType;
use App\Service\Formatter\PriceFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PriceFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{PriceType, int, string}>
     */
    public static function prices(): iterable
    {
        yield 'бесплатно' => [PriceType::Free, 0, 'Бесплатно'];
        yield 'бесплатно с суммой' => [PriceType::Free, 1000, 'Бесплатно'];
        yield 'платно, разряды' => [PriceType::Paid, 3500, '3 500 ₽ / мес'];
        yield 'пушкинская карта' => [PriceType::Pushkin, 2500, '2 500 ₽ / мес'];
        yield 'меньше тысячи' => [PriceType::Paid, 900, '900 ₽ / мес'];
        yield 'миллион' => [PriceType::Paid, 1000000, '1 000 000 ₽ / мес'];
    }

    #[DataProvider('prices')]
    public function testFormat(PriceType $priceType, int $amount, string $expected): void
    {
        self::assertSame($expected, (new PriceFormatter())->format($priceType, $amount));
    }
}
