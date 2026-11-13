<?php

declare(strict_types=1);

namespace App\Dto\Activity;

/**
 * Ребёнок в заявке (`forChild`) — формат `Child` группы E.
 */
final readonly class EnrollmentChildResponse
{
    public function __construct(
        public string $id,
        public string $firstName,
        public ?string $lastName,
        public int $age,
        public string $gender,
    ) {
    }
}
