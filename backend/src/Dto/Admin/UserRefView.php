<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\User;

final readonly class UserRefView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->getId()->toRfc4122(), $user->getName(), $user->getEmail());
    }
}
