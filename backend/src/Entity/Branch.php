<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\DayOfWeek;
use App\Repository\BranchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BranchRepository::class)]
#[ORM\Table(name: 'branch')]
class Branch
{
    public const string DEFAULT_NAME = 'Основной';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'branches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Club $club;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone;

    /** Если задано («По договорённости»), в API выводится вместо слотов. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $scheduleNote;

    #[ORM\Column]
    private int $position = 0;

    /** Источник при одобрении активности — по нему входящие заявки сопоставляются с её филиалами. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ActivityBranch $sourceActivityBranch;

    /** @var Collection<int, ScheduleSlot> */
    #[ORM\OneToMany(targetEntity: ScheduleSlot::class, mappedBy: 'branch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $slots;

    public function __construct(
        string $name,
        string $address,
        ?string $phone = null,
        ?string $scheduleNote = null,
        ?ActivityBranch $sourceActivityBranch = null,
    ) {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->address = $address;
        $this->phone = $phone;
        $this->scheduleNote = $scheduleNote;
        $this->sourceActivityBranch = $sourceActivityBranch;
        $this->slots = new ArrayCollection();
    }

    public static function fromActivityBranch(ActivityBranch $source): self
    {
        $branch = new self($source->getName(), $source->getAddress(), $source->getPhone(), null, $source);
        foreach ($source->getSlots() as $slot) {
            $branch->addSlot($slot->copy());
        }

        return $branch;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getClub(): Club
    {
        return $this->club;
    }

    public function belongsTo(Club $club): bool
    {
        return $this->club->getId()->equals($club->getId());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getScheduleNote(): ?string
    {
        return $this->scheduleNote;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getSourceActivityBranch(): ?ActivityBranch
    {
        return $this->sourceActivityBranch;
    }

    /**
     * @return list<ScheduleSlot>
     */
    public function getSlots(): array
    {
        return ScheduleSlot::sort($this->slots);
    }

    public function addSlot(ScheduleSlot $slot): static
    {
        if (!$this->slots->contains($slot)) {
            $slot->attachToBranch($this);
            $this->slots->add($slot);
        }

        return $this;
    }

    /**
     * @return list<DayOfWeek>
     */
    public function getDays(): array
    {
        return ScheduleSlot::days($this->slots);
    }

    public function attachToClub(Club $club, int $position): void
    {
        $this->club = $club;
        $this->position = $position;
    }
}
