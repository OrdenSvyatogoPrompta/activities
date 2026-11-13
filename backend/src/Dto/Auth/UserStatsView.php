<?php

declare(strict_types=1);

namespace App\Dto\Auth;

final readonly class UserStatsView
{
    public function __construct(
        /** Только активные (pending, confirmed). */
        public int $enrollments,
        public int $activities,
        public int $children,
    ) {
    }
}
