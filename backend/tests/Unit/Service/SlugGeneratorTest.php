<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SlugGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SlugGeneratorTest extends TestCase
{
    private SlugGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SlugGenerator(new AsciiSlugger());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'кириллица' => ['Акварель', 'akvarel'];
        yield 'мягкий знак не даёт лишний дефис' => ['Настольные игры', 'nastolnyye-igry'];
        yield 'латиница' => ['English Club VL', 'english-club-vl'];
        yield 'символы и эмодзи' => ['♟️ Шахматы!!', 'shakhmaty'];
        yield 'только эмодзи' => ['🎯', SlugGenerator::FALLBACK];
        yield 'пустая строка' => ['', SlugGenerator::FALLBACK];
    }

    #[DataProvider('names')]
    public function testSlugify(string $name, string $expected): void
    {
        self::assertSame($expected, $this->generator->slugify($name));
    }

    public function testFreeSlugIsUsedAsIs(): void
    {
        self::assertSame('akvarel', $this->generator->generate('Акварель', static fn (): bool => false));
    }

    public function testTakenSlugGetsNumericSuffix(): void
    {
        $taken = ['akvarel' => true, 'akvarel-2' => true];

        self::assertSame(
            'akvarel-3',
            $this->generator->generate('Акварель', static fn (string $slug): bool => isset($taken[$slug])),
        );
    }

    public function testLongSlugIsTruncatedWithRoomForSuffix(): void
    {
        $name = str_repeat('Очень длинное название ', 20);

        $slug = $this->generator->slugify($name);
        self::assertLessThanOrEqual(SlugGenerator::MAX_LENGTH, \strlen($slug));
        self::assertStringEndsNotWith('-', $slug);

        $withSuffix = $this->generator->generate($name, static fn (string $candidate): bool => $candidate === $slug);
        self::assertLessThanOrEqual(SlugGenerator::MAX_LENGTH, \strlen($withSuffix));
        self::assertStringEndsWith('-2', $withSuffix);
    }
}
