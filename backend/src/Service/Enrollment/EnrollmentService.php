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
use App\Repository\ChildRepository;
use App\Repository\ClubRepository;
use App\Repository\EnrollmentRepository;
use App\Workflow\EnrollmentWorkflow;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Заявки пользователя (группа F): подача, список своих заявок, отмена.
 */
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

    /**
     * Подаёт заявку в статусе pending.
     *
     * @throws HttpException 404 — кружок не найден или не опубликован;
     *                       422 — филиал не указан при нескольких / чужой, ребёнок не найден / чужой;
     *                       409 — у участника уже есть активная заявка в этот кружок
     */
    public function create(User $user, CreateEnrollmentRequest $request): Enrollment
    {
        $club = $this->clubs->findOnePublishedBySlug($request->clubId)
            ?? throw new NotFoundHttpException('Кружок не найден');

        $branch = $this->resolveBranch($club, $request);
        $child = $this->resolveChild($user, $request);

        // Защита от гонки дублей: две одновременные заявки одного участника обе прошли бы
        // проверку findActiveForParticipant() и обе сохранились бы. Уникального индекса
        // на «активную заявку участника» в схеме нет, symfony/lock не установлен, поэтому
        // сериализуем подачу заявок одного пользователя блокировкой строки users
        // (SELECT … FOR UPDATE) на время «проверка + вставка». Заявки разных
        // пользователей друг друга не ждут.
        //
        // Конфликт не бросаем изнутри wrapInTransaction(): при исключении Doctrine закрывает
        // EntityManager. Замыкание возвращает null, транзакция фиксируется пустой.
        $enrollment = $this->entityManager->wrapInTransaction(function () use ($user, $club, $branch, $child): ?Enrollment {
            $this->entityManager->find(User::class, $user->getId(), LockMode::PESSIMISTIC_WRITE);

            if (null !== $this->enrollments->findActiveForParticipant($user, $club, $child)) {
                return null;
            }

            $enrollment = new Enrollment($user, $club, $branch, $child);
            $this->entityManager->persist($enrollment);

            return $enrollment;
        });

        return $enrollment ?? throw new ConflictHttpException(null === $child
            ? 'Вы уже записаны в этот кружок: заявка на рассмотрении или подтверждена'
            : \sprintf('%s уже записан(а) в этот кружок: заявка на рассмотрении или подтверждена', $child->getFirstName()));
    }

    /**
     * Свои заявки, новые первыми.
     *
     * @param list<EnrollmentStatus>|null $statuses null — все, кроме cancelled
     *
     * @return list<Enrollment>
     */
    public function listOwn(User $user, ?array $statuses): array
    {
        return $this->enrollments->findByUser($user, $statuses);
    }

    /**
     * Отмена заявки автором через workflow (переход cancel).
     *
     * Заявку, которую пользователь не может видеть (не автор, не владелец кружка, не админ),
     * отдаём как 404, а не 403: так id чужих заявок нельзя перебрать, чтобы узнать об их
     * существовании. Владелец кружка и админ заявку видят (входящие, админка), поэтому
     * им — честный 403 от guard workflow.
     *
     * @throws HttpException 404 — заявки нет или она чужая; 403 — видна, но отменять может
     *                       только автор; 409 — заявка уже отменена или отклонена
     */
    public function cancel(User $user, string $id): Enrollment
    {
        $enrollment = $this->enrollments->findOneWithDetails($id);
        if (null === $enrollment || !($enrollment->isAuthoredBy($user) || $enrollment->canBeModeratedBy($user))) {
            throw new NotFoundHttpException('Заявка не найдена');
        }

        $this->transitions->apply($enrollment, EnrollmentWorkflow::TRANSITION_CANCEL);
        $this->entityManager->flush();

        return $enrollment;
    }

    private function resolveBranch(Club $club, CreateEnrollmentRequest $request): Branch
    {
        if (null === $request->branchId || '' === $request->branchId) {
            return $club->getSingleBranch()
                ?? throw $this->unprocessable($request, 'branchId', $request->branchId, 'Выберите филиал: у кружка их несколько');
        }

        return $club->findBranch($request->branchId)
            ?? throw $this->unprocessable($request, 'branchId', $request->branchId, 'Филиал не относится к этому кружку');
    }

    private function resolveChild(User $user, CreateEnrollmentRequest $request): ?Child
    {
        if (null === $request->childId || '' === $request->childId) {
            return null;
        }

        // Чужой ребёнок неотличим от несуществующего — не раскрываем чужие id
        return $this->children->findOneOwnedBy($request->childId, $user)
            ?? throw $this->unprocessable($request, 'childId', $request->childId, 'Ребёнок не найден среди ваших детей');
    }

    /**
     * 422 в том же формате, что ошибки #[MapRequestPayload]: ProblemFactory найдёт
     * ValidationFailedException в цепочке и отдаст `violations`.
     */
    private function unprocessable(CreateEnrollmentRequest $request, string $propertyPath, mixed $invalidValue, string $message): UnprocessableEntityHttpException
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], $request, $propertyPath, $invalidValue),
        ]);

        return new UnprocessableEntityHttpException($message, new ValidationFailedException($request, $violations));
    }
}
