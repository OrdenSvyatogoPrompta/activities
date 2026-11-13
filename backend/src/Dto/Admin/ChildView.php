<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\Child;

/**
 * Ребёнок в заявке: `{ id, firstName, lastName, age, gender }`.
 */
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

    public static function fromChild(Child $child): self
    {
        return new self(
            $child->getId()->toRfc4122(),
            $child->getFirstName(),
            $child->getLastName(),
            $child->getAge(),
            $child->getGender()->value,
        );
    }
}
