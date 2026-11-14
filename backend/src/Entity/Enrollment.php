<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\EnrollmentStatus;
use App\Repository\EnrollmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Статус меняется только через workflow `enrollment` (config/packages/workflow.yaml).
 */
#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Table(name: 'enrollment')]
#[ORM\Index(name: 'idx_enrollment_club_status', columns: ['club_id', 'status'])]
class Enrollment
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Club $club;

    /** null, только если филиал удалён после подачи заявки. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Branch $branch;

    /** Ребёнок; null — заявка «для себя». */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Child $child;

    #[ORM\Column(length: 16, enumType: EnrollmentStatus::class)]
    private EnrollmentStatus $status = EnrollmentStatus::Pending;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $rejectReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $statusChangedAt;

    /**
     * @param \DateTimeImmutable|null $createdAt для фикстур
     */
    public function __construct(User $user, Club $club, ?Branch $branch, ?Child $child = null, ?\DateTimeImmutable $createdAt = null)
    {
        if (null !== $branch && !$branch->belongsTo($club)) {
            throw new \InvalidArgumentException('Филиал не принадлежит кружку');
        }
        if (null !== $child && !$child->belongsTo($user)) {
            throw new \InvalidArgumentException('Ребёнок не принадлежит автору заявки');
        }

        $this->id = Uuid::v7();
        $this->user = $user;
        $this->club = $club;
        $this->branch = $branch;
        $this->child = $child;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->statusChangedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isAuthoredBy(User $user): bool
    {
        return $this->user->equals($user);
    }

    public function getClub(): Club
    {
        return $this->club;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function getChild(): ?Child
    {
        return $this->child;
    }

    public function getStatus(): EnrollmentStatus
    {
        return $this->status;
    }

    /**
     * Заявки в кружки без владельца модерирует только администратор.
     */
    public function canBeModeratedBy(User $user): bool
    {
        return $user->isAdmin() || $this->club->isOwnedBy($user);
    }

    public function canBeCancelledBy(User $user): bool
    {
        return $this->isAuthoredBy($user);
    }

    public function getRejectReason(): ?string
    {
        return $this->rejectReason;
    }

    public function setRejectReason(?string $rejectReason): static
    {
        $rejectReason = null === $rejectReason ? null : trim($rejectReason);
        $this->rejectReason = '' === $rejectReason ? null : $rejectReason;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @internal marking store workflow
     */
    public function getMarking(): string
    {
        return $this->status->value;
    }

    /**
     * @internal marking store workflow
     *
     * @param array<string, mixed> $context
     */
    public function setMarking(string $marking, array $context = []): void
    {
        $status = EnrollmentStatus::from($marking);
        if ($status !== $this->status) {
            $this->status = $status;
            $this->statusChangedAt = new \DateTimeImmutable();
        }
    }
}
