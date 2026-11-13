<?php

declare(strict_types=1);

namespace App\Dto\Auth;

/**
 * Пользователь в ответах API: `{ id, name, email, roles, stats }`.
 */
final readonly class UserView
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public array $roles,
        public UserStatsView $stats,
    ) {
    }
}
