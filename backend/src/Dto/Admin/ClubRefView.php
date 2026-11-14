<?php

declare(strict_types=1);

namespace App\Dto\Admin;

/**
 * `id` — slug кружка.
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
