<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Dto\Auth\UserStatsView;
use App\Dto\Auth\UserView;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ChildRepository;
use App\Repository\EnrollmentRepository;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Собирает представление пользователя для ответов API (`user` в auth-ответах, GET/PATCH /api/me).
 */
final readonly class UserViewFactory
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private ActivityRepository $activities,
        private ChildRepository $children,
        private NormalizerInterface $normalizer,
    ) {
    }

    public function create(User $user): UserView
    {
        return new UserView(
            id: $user->getId()->toRfc4122(),
            name: $user->getName(),
            email: $user->getEmail(),
            roles: $user->getRoles(),
            stats: new UserStatsView(
                enrollments: $this->enrollments->countActiveByUser($user),
                activities: $this->activities->countByOwner($user),
                children: $this->children->countByParent($user),
            ),
        );
    }

    /**
     * Для мест, где ответ собирается массивом без Serializer (ответ lexik при входе).
     *
     * @return array<string, mixed>
     */
    public function createArray(User $user): array
    {
        $data = $this->normalizer->normalize($this->create($user), 'json');
        \assert(\is_array($data));

        return $data;
    }
}
