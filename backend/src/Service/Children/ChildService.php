<?php

declare(strict_types=1);

namespace App\Service\Children;

use App\Dto\Children\CreateChildRequest;
use App\Dto\Children\UpdateChildRequest;
use App\Entity\Child;
use App\Entity\Enum\Gender;
use App\Entity\User;
use App\Repository\ChildRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Дети пользователя (группа E): список, добавление, редактирование, удаление.
 *
 * Доступ только к своим детям: чужой, несуществующий и некорректный id одинаково дают 404,
 * чтобы не раскрывать существование чужих записей.
 */
final readonly class ChildService
{
    public function __construct(
        private ChildRepository $children,
        private EnrollmentRepository $enrollments,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<Child>
     */
    public function list(User $parent): array
    {
        return $this->children->findByParent($parent);
    }

    public function create(User $parent, CreateChildRequest $request): Child
    {
        $child = new Child(
            $parent,
            trim($request->firstName),
            $request->lastName,
            $request->age,
            Gender::from($request->gender),
        );

        $this->em->persist($child);
        $this->em->flush();

        return $child;
    }

    /**
     * @param bool $lastNameProvided ключ `lastName` есть в теле запроса (null/"" — стереть фамилию)
     */
    public function update(User $parent, Uuid|string $id, UpdateChildRequest $request, bool $lastNameProvided): Child
    {
        $child = $this->get($parent, $id);

        if (null !== $request->firstName) {
            $child->setFirstName(trim($request->firstName));
        }
        if ($lastNameProvided) {
            $child->setLastName($request->lastName);
        }
        if (null !== $request->age) {
            $child->setAge($request->age);
        }
        if (null !== $request->gender) {
            $child->setGender(Gender::from($request->gender));
        }

        $this->em->flush();

        return $child;
    }

    /**
     * Удаление запрещено, пока у ребёнка есть активные заявки (pending/confirmed).
     * Завершённые заявки (rejected/cancelled) удаляются вместе с ребёнком (ON DELETE CASCADE).
     */
    public function delete(User $parent, Uuid|string $id): void
    {
        $child = $this->get($parent, $id);

        if ($this->enrollments->hasActiveForChild($child)) {
            throw new ConflictHttpException('Сначала отмените записи ребёнка');
        }

        $this->em->remove($child);
        $this->em->flush();
    }

    public function get(User $parent, Uuid|string $id): Child
    {
        return $this->children->findOneOwnedBy($id, $parent)
            ?? throw new NotFoundHttpException('Ребёнок не найден');
    }
}
