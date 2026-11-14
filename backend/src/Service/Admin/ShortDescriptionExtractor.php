<?php

declare(strict_types=1);

namespace App\Service\Admin;

/**
 * Первое предложение описания; длинное обрезается по границе слова с многоточием.
 */
final class ShortDescriptionExtractor
{
    public const int MAX_LENGTH = 160;
    private const string ELLIPSIS = '…';

    public function extract(string $description): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $description));
        if ('' === $text) {
            return '';
        }

        if (preg_match('/^.+?[.!?…]+(?=\s|$)/u', $text, $matches)) {
            $text = $matches[0];
        }

        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::MAX_LENGTH - mb_strlen(self::ELLIPSIS));
        $lastSpace = mb_strrpos($cut, ' ');
        if (false !== $lastSpace && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t,;:—–-").self::ELLIPSIS;
    }
}
