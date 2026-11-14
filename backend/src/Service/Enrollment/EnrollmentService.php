<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Dto\Enrollment\CreateEnrollmentRequest;
use App\Entity\Branch;
use App\Entity\Child;
use App\Entity\Club;
use App\Entity\Enrollment;
use App\Entity\Enum\EnrollmentStatus;
use App\Entity\User;
use App\Http\Problem\ValidationProblem;
use App\Repository\ChildRepository;
use App\Repository\ClubRepository;
use App\Repository\EnrollmentRepository;
use App\Workflow\EnrollmentWorkflow;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class EnrollmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClubRepository $clubs,
        private ChildRepository $children,
        private EnrollmentRepository $enrollments,
        private EnrollmentTransitionApplier $transitions,
    ) {
    }

    public function create(User $user, CreateEnrollmentRequest $request): Enrollment
    {
        $club = $this->clubs->findOnePublishedBySlug($request->clubId)
            ?? throw new NotFoundHttpException('Кружок не найден');

        $branch = $this->resolveBranch($club, $request);
        $child = $this->resolveChild($user, $request);

        // Блокировка строки пользователя сериализует его «проверка дубля + вставка»:
        // уникального индекса на активную заявку нет. Конфликт бросаем после транзакции —
        // исключение внутри wrapInTransaction() закрыло бы EntityManager.
        $enrollment = $this->entityManager->wrapInTransaction(function () use ($user, $club, $branch, $child): ?Enrollment {
            $this->entityManager->find(User::class, $user->getId(), LockMode::PESSIMISTIC_WRITE);

            if (null !== $this->enrollments->findActiveForParticipant($user, $club, $child)) {
                return null;
            }

            $enrollment = new Enrollment($user, $club, $branch, $child);
            $this->entityManager->persist($enrollment);

            return $enrollment;
        });

        return $enrollment ?? throw new ConflictHttpException(null === $child ? 'Вы уже записаны в этот кружок: заявка на рассмотрении или подтверждена' : \sprintf('%s уже записан(а) в этот кружок: заявка на рассмотрении или подтверждена', $child->getFirstName()));
    }

    /**
     * @param list<EnrollmentStatus>|null $statuses null — все, кроме cancelled
     *
     * @return list<Enrollment>
     */
    public function listOwn(User $user, ?array $statuses): array
    {
        return $this->enrollments->findByUser($user, $statuses);
    }

    public function cancel(string $id): Enrollment
    {
        return $this->transitions->apply($id, EnrollmentWorkflow::TRANSITION_CANCEL);
    }

    private function resolveBranch(Club $club, CreateEnrollmentRequest $request): Branch
    {
        if (null === $request->branchId || '' === $request->branchId) {
            return $club->getSingleBranch()
                ?? throw ValidationProblem::forProperty($request, 'branchId', $request->branchId, 'Выберите филиал: у кружка их несколько');
        }

        return $club->findBranch($request->branchId)
            ?? throw ValidationProblem::forProperty($request, 'branchId', $request->branchId, 'Филиал не относится к этому кружку');
    }

    private function resolveChild(User $user, CreateEnrollmentRequest $request): ?Child
    {
        if (null === $request->childId || '' === $request->childId) {
            return null;
        }

        return $this->children->findOneOwnedBy($request->childId, $user)
            ?? throw ValidationProblem::forProperty($request, 'childId', $request->childId, 'Ребёнок не найден среди ваших детей');
    }
}
