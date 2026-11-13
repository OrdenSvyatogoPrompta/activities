<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Транслитерация названия; занятый slug получает суффикс `-2`, `-3` и т. д.
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
     * @param callable(string): bool $isTaken
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

    public function slugify(string $source): string
    {
        // Иначе ь/ъ станут разделителем: «nastol-nyye»
        $source = str_replace(['ь', 'Ь', 'ъ', 'Ъ'], '', $source);

        $slug = $this->slugger->slug($source, '-', 'ru')->lower()->toString();
        $slug = rtrim(substr($slug, 0, self::MAX_LENGTH), '-');

        return '' === $slug ? self::FALLBACK : $slug;
    }
}
