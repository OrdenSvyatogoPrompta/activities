<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Dto\Auth\UpdateProfileRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProfileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function update(User $user, UpdateProfileRequest $request): void
    {
        if (null !== $request->name) {
            $user->setName(trim($request->name));
        }

        $this->entityManager->flush();
    }
}
