<?php

declare(strict_types=1);

namespace App\Dto\Admin;

/**
 * Кружок в списке заявок для администратора: `id` — slug; `owner` null — кружок без владельца,
 * заявки в него подтверждает администратор.
 */
final readonly class ClubRefView
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $isPublished,
        public ?UserRefView $owner,
    ) {
    }
}
