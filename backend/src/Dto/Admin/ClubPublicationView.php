<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\Club;

final readonly class ClubPublicationView
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $isPublished,
    ) {
    }

    public static function fromClub(Club $club): self
    {
        return new self($club->getSlug(), $club->getName(), $club->isPublished());
    }
}
