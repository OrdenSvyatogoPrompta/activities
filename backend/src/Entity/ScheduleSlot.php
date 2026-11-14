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
 * Принадлежит ровно одному владельцу: филиалу кружка или филиалу активности.
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

    public function copy(): self
    {
        return new self($this->day, $this->timeFrom, $this->timeTo);
    }

    public function getDay(): DayOfWeek
    {
        return $this->day;
    }

    public function getTimeFromString(): string
    {
        return $this->timeFrom->format('H:i');
    }

    public function getTimeToString(): string
    {
        return $this->timeTo->format('H:i');
    }

    public function sortKey(): string
    {
        return $this->day->isoNumber().$this->getTimeFromString().$this->getTimeToString();
    }

    /**
     * По дню недели и времени.
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
     * Уникальные дни слотов, Пн…Вс.
     *
     * @param iterable<self> $slots
     *
     * @return list<DayOfWeek>
     */
    public static function days(iterable $slots): array
    {
        $days = [];
        foreach ($slots as $slot) {
            $days[] = $slot->day;
        }

        return DayOfWeek::sorted($days);
    }

    public function attachToBranch(Branch $branch): void
    {
        if (null !== $this->activityBranch) {
            throw new \LogicException('Слот уже принадлежит филиалу активности');
        }
        $this->branch = $branch;
    }

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
