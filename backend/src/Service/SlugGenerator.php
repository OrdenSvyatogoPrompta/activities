<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Slug кружка из названия: транслитерация (AsciiSlugger, локаль ru), нижний регистр,
 * уникальность через суффикс `-2`, `-3`, …
 *
 * Пример: `$slugGenerator->generate('Акварель', $clubRepository->slugExists(...))` → `akvarel`.
 */
final class SlugGenerator
{
    public const int MAX_LENGTH = 100;
    public const string FALLBACK = 'club';

    public function __construct(
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @param callable(string): bool $isTaken занят ли slug (обычно ClubRepository::slugExists)
     */
    public function generate(string $source, callable $isTaken): string
    {
        $base = $this->slugify($source);

        $candidate = $base;
        for ($suffix = 2; $isTaken($candidate); ++$suffix) {
            $tail = '-'.$suffix;
            $candidate = rtrim(substr($base, 0, self::MAX_LENGTH - \strlen($tail)), '-').$tail;
        }

        return $candidate;
    }

    /**
     * Slug без проверки уникальности.
     */
    public function slugify(string $source): string
    {
        // Мягкий и твёрдый знаки транслитерируются в апостроф, который слаггер превращает
        // в разделитель («nastol-nyye»); убираем их заранее
        $source = str_replace(['ь', 'Ь', 'ъ', 'Ъ'], '', $source);

        $slug = $this->slugger->slug($source, '-', 'ru')->lower()->toString();
        $slug = rtrim(substr($slug, 0, self::MAX_LENGTH), '-');

        return '' === $slug ? self::FALLBACK : $slug;
    }
}
