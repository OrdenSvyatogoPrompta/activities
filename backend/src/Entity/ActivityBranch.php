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
 * Филиал активности (заявки организатора). При одобрении копируется в Branch кружка,
 * связь сохраняется в Branch::$sourceActivityBranch.
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

    /** Порядок филиала в активности, с 0. */
    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, ScheduleSlot> */
    #[ORM\OneToMany(targetEntity: ScheduleSlot::class, mappedBy: 'activityBranch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $slots;

    /**
     * Филиал привязывается к активности через Activity::addBranch().
     *
     * @param string $name пустое имя заменяется на «Основной»
     */
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

    public function getActivity(): Activity
    {
        return $this->activity;
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
            $slot->attachToActivityBranch($this);
            $this->slots->add($slot);
        }

        return $this;
    }

    /**
     * Дни занятий в порядке Пн…Вс.
     *
     * @return list<DayOfWeek>
     */
    public function getDays(): array
    {
        return DayOfWeek::sorted(array_map(static fn (ScheduleSlot $slot): DayOfWeek => $slot->getDay(), $this->slots->toArray()));
    }

    /**
     * Первый слот (по дню и времени) — для совместимых полей `timeFrom/timeTo` в API.
     */
    public function getFirstSlot(): ?ScheduleSlot
    {
        return $this->getSlots()[0] ?? null;
    }

    /**
     * @internal вызывается из Activity::addBranch()
     */
    public function attachToActivity(Activity $activity, int $position): void
    {
        $this->activity = $activity;
        $this->position = $position;
    }
}
