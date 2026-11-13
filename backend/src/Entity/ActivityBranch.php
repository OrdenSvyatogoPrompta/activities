<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\DayOfWeek;
use App\Repository\ActivityBranchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * При одобрении активности копируется в Branch кружка (Branch::$sourceActivityBranch).
 */
#[ORM\Entity(repositoryClass: ActivityBranchRepository::class)]
#[ORM\Table(name: 'activity_branch')]
class ActivityBranch
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'branches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, ScheduleSlot> */
    #[ORM\OneToMany(targetEntity: ScheduleSlot::class, mappedBy: 'activityBranch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $slots;

    public function __construct(string $name, string $address, ?string $phone = null)
    {
        $this->id = Uuid::v7();
        $this->name = '' === trim($name) ? Branch::DEFAULT_NAME : $name;
        $this->address = $address;
        $this->phone = $phone;
        $this->slots = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function getPosition(): int
    {
        return $this->position;
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
            $slot->attachToActivityBranch($this);
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

    public function getFirstSlot(): ?ScheduleSlot
    {
        return $this->getSlots()[0] ?? null;
    }

    public function attachToActivity(Activity $activity, int $position): void
    {
        $this->activity = $activity;
        $this->position = $position;
    }
}
