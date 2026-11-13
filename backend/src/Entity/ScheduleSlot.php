<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\DayOfWeek;
use App\Repository\ScheduleSlotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Слот расписания: день недели и время «с — по» (`timeFrom < timeTo`).
 *
 * Принадлежит ровно одному владельцу — филиалу кружка (Branch) или филиалу активности
 * (ActivityBranch); владелец назначается через Branch::addSlot() / ActivityBranch::addSlot().
 * Время хранится как TIME, в DateTimeImmutable дата всегда 1970-01-01.
 */
#[ORM\Entity(repositoryClass: ScheduleSlotRepository::class)]
#[ORM\Table(name: 'schedule_slot')]
class ScheduleSlot
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Branch $branch = null;

    #[ORM\ManyToOne(inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ActivityBranch $activityBranch = null;

    #[ORM\Column(length: 2, enumType: DayOfWeek::class)]
    private DayOfWeek $day;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private \DateTimeImmutable $timeFrom;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private \DateTimeImmutable $timeTo;

    /**
     * @param \DateTimeImmutable|string $timeFrom время или строка `HH:MM`
     * @param \DateTimeImmutable|string $timeTo   время или строка `HH:MM`
     *
     * @throws \InvalidArgumentException если время некорректно или timeFrom >= timeTo
     */
    public function __construct(DayOfWeek $day, \DateTimeImmutable|string $timeFrom, \DateTimeImmutable|string $timeTo)
    {
        $from = self::normalizeTime($timeFrom);
        $to = self::normalizeTime($timeTo);
        if ($from >= $to) {
            throw new \InvalidArgumentException('Время начала занятия должно быть раньше времени окончания');
        }

        $this->id = Uuid::v7();
        $this->day = $day;
        $this->timeFrom = $from;
        $this->timeTo = $to;
    }

    /**
     * Копия слота без владельца — для переноса расписания активности в кружок при одобрении.
     */
    public function copy(): self
    {
        return new self($this->day, $this->timeFrom, $this->timeTo);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function getActivityBranch(): ?ActivityBranch
    {
        return $this->activityBranch;
    }

    public function getDay(): DayOfWeek
    {
        return $this->day;
    }

    public function getTimeFrom(): \DateTimeImmutable
    {
        return $this->timeFrom;
    }

    public function getTimeTo(): \DateTimeImmutable
    {
        return $this->timeTo;
    }

    /** `HH:MM` */
    public function getTimeFromString(): string
    {
        return $this->timeFrom->format('H:i');
    }

    /** `HH:MM` */
    public function getTimeToString(): string
    {
        return $this->timeTo->format('H:i');
    }

    /**
     * Ключ сортировки: день недели, затем время начала и окончания.
     */
    public function sortKey(): string
    {
        return $this->day->isoNumber().$this->getTimeFromString().$this->getTimeToString();
    }

    /**
     * Сортирует слоты по дню недели (Пн…Вс) и времени.
     *
     * @param iterable<self> $slots
     *
     * @return list<self>
     */
    public static function sort(iterable $slots): array
    {
        $list = [...$slots];
        usort($list, static fn (self $a, self $b): int => $a->sortKey() <=> $b->sortKey());

        return $list;
    }

    /**
     * @internal вызывается из Branch::addSlot()
     */
    public function attachToBranch(Branch $branch): void
    {
        if (null !== $this->activityBranch) {
            throw new \LogicException('Слот уже принадлежит филиалу активности');
        }
        $this->branch = $branch;
    }

    /**
     * @internal вызывается из ActivityBranch::addSlot()
     */
    public function attachToActivityBranch(ActivityBranch $activityBranch): void
    {
        if (null !== $this->branch) {
            throw new \LogicException('Слот уже принадлежит филиалу кружка');
        }
        $this->activityBranch = $activityBranch;
    }

    private static function normalizeTime(\DateTimeImmutable|string $time): \DateTimeImmutable
    {
        if ($time instanceof \DateTimeImmutable) {
            $time = $time->format('H:i');
        }

        if (1 !== preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            throw new \InvalidArgumentException(\sprintf('Некорректное время «%s», ожидается ЧЧ:ММ', $time));
        }

        $parsed = \DateTimeImmutable::createFromFormat('!H:i', $time);
        \assert($parsed instanceof \DateTimeImmutable);

        return $parsed;
    }
}
