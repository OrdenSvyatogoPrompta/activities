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

/**
 * Филиал кружка. У каждого кружка минимум один филиал; у сидовых кружков без филиалов
 * он называется «Основной» (DEFAULT_NAME).
 *
 * Расписание — слоты (ScheduleSlot); если задан scheduleNote (например, «По договорённости»),
 * в API выводится он вместо слотов (ScheduleFormatter).
 */
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $scheduleNote;

    /** Порядок филиала в кружке, с 0. */
    #[ORM\Column]
    private int $position = 0;

    /**
     * Филиал активности, из которого создан этот филиал при одобрении (для сопоставления
     * входящих заявок с филиалами активности). У сидовых кружков — null.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ActivityBranch $sourceActivityBranch;

    /** @var Collection<int, ScheduleSlot> */
    #[ORM\OneToMany(targetEntity: ScheduleSlot::class, mappedBy: 'branch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $slots;

    /**
     * Филиал привязывается к кружку через Club::addBranch().
     */
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

    /**
     * Копия филиала активности (с копиями слотов) для кружка, создаваемого при одобрении.
     */
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

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getScheduleNote(): ?string
    {
        return $this->scheduleNote;
    }

    public function setScheduleNote(?string $scheduleNote): static
    {
        $this->scheduleNote = $scheduleNote;

        return $this;
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
     * Слоты, отсортированные по дню недели и времени.
     *
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

    public function removeSlot(ScheduleSlot $slot): static
    {
        $this->slots->removeElement($slot);

        return $this;
    }

    /**
     * Дни занятий филиала в порядке Пн…Вс.
     *
     * @return list<DayOfWeek>
     */
    public function getDays(): array
    {
        return DayOfWeek::sorted(array_map(static fn (ScheduleSlot $slot): DayOfWeek => $slot->getDay(), $this->slots->toArray()));
    }

    /**
     * @internal вызывается из Club::addBranch()
     */
    public function attachToClub(Club $club, int $position): void
    {
        $this->club = $club;
        $this->position = $position;
    }
}
