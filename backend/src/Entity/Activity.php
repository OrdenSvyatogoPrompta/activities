<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\ActivityStatus;
use App\Entity\Enum\Category;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Заявка организатора на кружок: pending → approved (создаётся Club) | rejected.
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activity')]
#[ORM\Index(name: 'idx_activity_status', columns: ['status'])]
class Activity
{
    public const string DEFAULT_EMOJI = '🎯';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 16, enumType: Category::class)]
    private Category $category;

    #[ORM\Column(length: 16)]
    private string $emoji;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $ageMin;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $ageMax;

    #[ORM\Column(length: 16, enumType: VisitType::class)]
    private VisitType $visitType;

    #[ORM\Column(length: 16, enumType: PriceType::class)]
    private PriceType $priceType;

    /** ₽/мес; для free всегда 0. */
    #[ORM\Column]
    private int $priceAmount;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags;

    #[ORM\Column(length: 16, enumType: ActivityStatus::class)]
    private ActivityStatus $status = ActivityStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $moderationComment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $moderatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $moderatedBy = null;

    #[ORM\OneToOne(targetEntity: Club::class, mappedBy: 'sourceActivity')]
    private ?Club $club = null;

    /** @var Collection<int, ActivityBranch> */
    #[ORM\OneToMany(targetEntity: ActivityBranch::class, mappedBy: 'activity', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $branches;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<string> $tags
     */
    public function __construct(
        User $owner,
        string $name,
        Category $category,
        string $description,
        int $ageMin,
        int $ageMax,
        VisitType $visitType,
        PriceType $priceType,
        int $priceAmount,
        array $tags = [],
        ?string $emoji = null,
    ) {
        if ($ageMin > $ageMax) {
            throw new \InvalidArgumentException('Минимальный возраст больше максимального');
        }

        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->name = $name;
        $this->category = $category;
        $this->description = $description;
        $this->ageMin = $ageMin;
        $this->ageMax = $ageMax;
        $this->visitType = $visitType;
        $this->priceType = $priceType;
        $this->priceAmount = PriceType::Free === $priceType ? 0 : $priceAmount;
        $this->tags = array_values($tags);
        $this->emoji = null === $emoji || '' === trim($emoji) ? self::DEFAULT_EMOJI : $emoji;
        $this->branches = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getEmoji(): string
    {
        return $this->emoji;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAgeMin(): int
    {
        return $this->ageMin;
    }

    public function getAgeMax(): int
    {
        return $this->ageMax;
    }

    public function getVisitType(): VisitType
    {
        return $this->visitType;
    }

    public function getPriceType(): PriceType
    {
        return $this->priceType;
    }

    public function getPriceAmount(): int
    {
        return $this->priceAmount;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getStatus(): ActivityStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return ActivityStatus::Pending === $this->status;
    }

    public function getModerationComment(): ?string
    {
        return $this->moderationComment;
    }

    public function getModeratedAt(): ?\DateTimeImmutable
    {
        return $this->moderatedAt;
    }

    public function getModeratedBy(): ?User
    {
        return $this->moderatedBy;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    /**
     * @return Collection<int, ActivityBranch>
     */
    public function getBranches(): Collection
    {
        return $this->branches;
    }

    public function addBranch(ActivityBranch $branch): static
    {
        if (!$this->branches->contains($branch)) {
            $branch->attachToActivity($this, $this->branches->count());
            $this->branches->add($branch);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function approve(Club $club, User $moderator): void
    {
        $this->assertPending();
        if ($club->getSourceActivity() !== $this) {
            throw new \LogicException('Кружок создан не из этой активности');
        }

        $this->status = ActivityStatus::Approved;
        $this->club = $club;
        $this->moderationComment = null;
        $this->markModerated($moderator);
    }

    public function reject(string $comment, User $moderator): void
    {
        $this->assertPending();
        if ('' === trim($comment)) {
            throw new \InvalidArgumentException('Укажите причину отклонения');
        }

        $this->status = ActivityStatus::Rejected;
        $this->moderationComment = trim($comment);
        $this->markModerated($moderator);
    }

    private function assertPending(): void
    {
        if (!$this->isPending()) {
            throw new \LogicException('Модерировать можно только активность на рассмотрении');
        }
    }

    private function markModerated(User $moderator): void
    {
        $this->moderatedBy = $moderator;
        $this->moderatedAt = new \DateTimeImmutable();
    }
}
