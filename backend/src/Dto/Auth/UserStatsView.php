<?php

declare(strict_types=1);

namespace App\Dto\Auth;

/**
 * Счётчики для шапки личного кабинета.
 */
final readonly class UserStatsView
{
    public function __construct(
        /** Активные заявки (pending, confirmed). */
        public int $enrollments,
        /** Все активности организатора, в любом статусе. */
        public int $activities,
        public int $children,
    ) {
    }
}
