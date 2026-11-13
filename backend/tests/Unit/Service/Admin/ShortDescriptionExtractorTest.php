<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Admin;

use App\Service\Admin\ShortDescriptionExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShortDescriptionExtractorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function descriptions(): iterable
    {
        yield 'первое предложение' => [
            'Художественная студия для детей. Занятия акварелью, гуашью, пастелью.',
            'Художественная студия для детей.',
        ];
        yield 'восклицательный знак' => ['Играем вместе! Приходите всей семьёй.', 'Играем вместе!'];
        yield 'многоточие' => ['Шахматы… и не только. Ещё го.', 'Шахматы…'];
        yield 'одно предложение без точки' => ['Клуб настольных игр', 'Клуб настольных игр'];
        yield 'точка внутри числа не конец предложения' => ['Занятия с 2.5 лет. Группы по 6 человек.', 'Занятия с 2.5 лет.'];
        yield 'пробелы и переносы схлопываются' => ["  Студия\n\nрисования.  Вторая часть.", 'Студия рисования.'];
        yield 'пустое' => ['   ', ''];
    }

    #[DataProvider('descriptions')]
    public function testExtract(string $description, string $expected): void
    {
        self::assertSame($expected, (new ShortDescriptionExtractor())->extract($description));
    }

    public function testLongSentenceIsCutByWordWithEllipsis(): void
    {
        $description = str_repeat('очень ', 40).'длинное предложение. Второе.';

        $short = (new ShortDescriptionExtractor())->extract($description);

        self::assertLessThanOrEqual(ShortDescriptionExtractor::MAX_LENGTH, mb_strlen($short));
        self::assertStringEndsWith('очень…', $short);
    }

    public function testExactly160CharactersIsKept(): void
    {
        $sentence = str_repeat('а', 159).'.';

        self::assertSame($sentence, (new ShortDescriptionExtractor())->extract($sentence.' Дальше.'));
    }
}
