<?php

declare(strict_types=1);

namespace App\Dto\Children;

use App\Entity\Child;

final readonly class ChildView
{
    public function __construct(
        public string $id,
        public string $firstName,
        public ?string $lastName,
        public int $age,
        public string $gender,
    ) {
    }

    public static function fromEntity(Child $child): self
    {
        return new self(
            id: $child->getId()->toRfc4122(),
            firstName: $child->getFirstName(),
            lastName: $child->getLastName(),
            age: $child->getAge(),
            gender: $child->getGender()->value,
        );
    }
}
