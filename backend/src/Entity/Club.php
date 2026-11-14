<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Repository\ClubRepository;
use App\Repository\Uuids;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;

/**
 * В API `id` кружка — slug.
 */
#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[ORM\Table(name: 'club')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Кружок с таким адресом уже существует')]
class Club
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 120, unique: true)]
    private string $slug;

    #[ORM\Column(length: 100)]
    private string $name;

    /** @var list<string> значения Category */
    #[ORM\Column(type: Types::JSON)]
    private array $categories;

    #[ORM\Column(length: 16)]
    private string $emoji;

    #[ORM\Column(length: 7)]
    private string $color;

    #[ORM\Column(length: 160)]
    private string $shortDescription;

    #[ORM\Column(type: Types::TEXT)]
    private string $fullDescription;

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

    #[ORM\Column]
    private bool $isPublished;

    /** null — кружок без владельца: заявки в него некому подтвердить. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner;

    #[ORM\OneToOne(inversedBy: 'club')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Activity $sourceActivity;

    /** @var Collection<int, Branch> */
    #[ORM\OneToMany(targetEntity: Branch::class, mappedBy: 'club', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $branches;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<Category> $categories
     * @param list<string>   $tags
     */
    public function __construct(
        string $slug,
        string $name,
        array $categories,
        string $emoji,
        string $color,
        string $shortDescription,
        string $fullDescription,
        int $ageMin,
        int $ageMax,
        VisitType $visitType,
        PriceType $priceType,
        int $priceAmount,
        array $tags = [],
        ?User $owner = null,
        ?Activity $sourceActivity = null,
        bool $isPublished = true,
    ) {
        $this->id = Uuid::v7();
        $this->slug = $slug;
        $this->name = $name;
        $this->setCategories($categories);
        $this->emoji = $emoji;
        $this->color = $color;
        $this->shortDescription = $shortDescription;
        $this->fullDescription = $fullDescription;
        $this->setAgeRange($ageMin, $ageMax);
        $this->visitType = $visitType;
        $this->setPrice($priceType, $priceAmount);
        $this->setTags($tags);
        $this->owner = $owner;
        $this->sourceActivity = $sourceActivity;
        $this->isPublished = $isPublished;
        $this->branches = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return list<Category>
     */
    public function getCategories(): array
    {
        return array_map(static fn (string $value): Category => Category::from($value), $this->categories);
    }

    /**
     * @param list<Category> $categories
     */
    private function setCategories(array $categories): static
    {
        if ([] === $categories) {
            throw new \InvalidArgumentException('У кружка должна быть хотя бы одна категория');
        }
        $this->categories = array_values(array_unique(array_map(static fn (Category $category): string => $category->value, $categories)));

        return $this;
    }

    public function getEmoji(): string
    {
        return $this->emoji;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function getFullDescription(): string
    {
        return $this->fullDescription;
    }

    public function getAgeMin(): int
    {
        return $this->ageMin;
    }

    public function getAgeMax(): int
    {
        return $this->ageMax;
    }

    private function setAgeRange(int $ageMin, int $ageMax): static
    {
        if ($ageMin > $ageMax) {
            throw new \InvalidArgumentException('Минимальный возраст больше максимального');
        }
        $this->ageMin = $ageMin;
        $this->ageMax = $ageMax;

        return $this;
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

    public function setPrice(PriceType $priceType, int $priceAmount): static
    {
        $this->priceType = $priceType;
        $this->priceAmount = PriceType::Free === $priceType ? 0 : $priceAmount;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param list<string> $tags
     */
    private function setTags(array $tags): static
    {
        $this->tags = array_values($tags);

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function isOwnedBy(User $user): bool
    {
        return $user->equals($this->owner);
    }

    public function getSourceActivity(): ?Activity
    {
        return $this->sourceActivity;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function getBranches(): Collection
    {
        return $this->branches;
    }

    /**
     * null, если филиалов несколько.
     */
    public function getSingleBranch(): ?Branch
    {
        return 1 === $this->branches->count() ? $this->branches->first() : null;
    }

    public function findBranch(string $id): ?Branch
    {
        $uuid = Uuids::parse($id);
        foreach ($this->branches as $branch) {
            if (null !== $uuid && $branch->getId()->equals($uuid)) {
                return $branch;
            }
        }

        return null;
    }

    public function addBranch(Branch $branch): static
    {
        if (!$this->branches->contains($branch)) {
            $position = 0;
            foreach ($this->branches as $existing) {
                $position = max($position, $existing->getPosition() + 1);
            }
            $branch->attachToClub($this, $position);
            $this->branches->add($branch);
        }

        return $this;
    }

    /**
     * Объединение дней всех филиалов.
     *
     * @return list<DayOfWeek>
     */
    public function getDays(): array
    {
        $days = [];
        foreach ($this->branches as $branch) {
            array_push($days, ...$branch->getDays());
        }

        return DayOfWeek::sorted($days);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
