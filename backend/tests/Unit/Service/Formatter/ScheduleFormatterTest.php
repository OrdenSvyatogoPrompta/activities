<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Formatter;

use App\Entity\ActivityBranch;
use App\Entity\Branch;
use App\Entity\Enum\DayOfWeek;
use App\Entity\ScheduleSlot;
use App\Service\Formatter\ScheduleFormatter;
use PHPUnit\Framework\TestCase;

final class ScheduleFormatterTest extends TestCase
{
    private ScheduleFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ScheduleFormatter();
    }

    public function testGroupsSlotsWithSameTime(): void
    {
        // Филиал «Борисенко» кружка swimming-dyush; слоты нарочно перемешаны
        $slots = [
            new ScheduleSlot(DayOfWeek::Thursday, '10:00', '12:00'),
            new ScheduleSlot(DayOfWeek::Friday, '15:00', '18:00'),
            new ScheduleSlot(DayOfWeek::Monday, '15:00', '18:00'),
            new ScheduleSlot(DayOfWeek::Tuesday, '10:00', '12:00'),
            new ScheduleSlot(DayOfWeek::Wednesday, '15:00', '18:00'),
        ];

        self::assertSame("Пн, Ср, Пт · 15:00–18:00\nВт, Чт · 10:00–12:00", $this->formatter->formatSlots($slots));
    }

    public function testGroupsAreOrderedByFirstDay(): void
    {
        $slots = [
            new ScheduleSlot(DayOfWeek::Saturday, '10:00', '12:00'),
            new ScheduleSlot(DayOfWeek::Thursday, '15:30', '17:30'),
            new ScheduleSlot(DayOfWeek::Tuesday, '15:30', '17:30'),
        ];

        self::assertSame("Вт, Чт · 15:30–17:30\nСб · 10:00–12:00", $this->formatter->formatSlots($slots));
    }

    public function testSameDayDifferentTimesAreSeparateGroupsOrderedByTime(): void
    {
        $slots = [
            new ScheduleSlot(DayOfWeek::Saturday, '14:00', '16:00'),
            new ScheduleSlot(DayOfWeek::Saturday, '09:00', '10:00'),
            new ScheduleSlot(DayOfWeek::Sunday, '09:00', '10:00'),
        ];

        self::assertSame("Сб, Вс · 09:00–10:00\nСб · 14:00–16:00", $this->formatter->formatSlots($slots));
    }

    public function testSingleGroup(): void
    {
        $slots = [
            new ScheduleSlot(DayOfWeek::Sunday, '11:00', '14:00'),
            new ScheduleSlot(DayOfWeek::Saturday, '11:00', '14:00'),
        ];

        self::assertSame('Сб, Вс · 11:00–14:00', $this->formatter->formatSlots($slots));
    }

    public function testNoSlotsGiveEmptyString(): void
    {
        self::assertSame('', $this->formatter->formatSlots([]));
    }

    public function testBranchScheduleNoteWins(): void
    {
        $branch = new Branch(Branch::DEFAULT_NAME, 'ул. Пушкинская, 36', null, 'По договорённости');
        $branch->addSlot(new ScheduleSlot(DayOfWeek::Monday, '10:00', '20:00'));

        self::assertSame('По договорённости', $this->formatter->formatBranch($branch));
    }

    public function testBranchWithoutNoteUsesSlots(): void
    {
        $branch = new Branch('Вторая Речка', 'ул. Нейбута, 32');
        $branch->addSlot(new ScheduleSlot(DayOfWeek::Friday, '16:00', '19:00'));
        $branch->addSlot(new ScheduleSlot(DayOfWeek::Monday, '16:00', '19:00'));

        self::assertSame('Пн, Пт · 16:00–19:00', $this->formatter->formatBranch($branch));
    }

    public function testActivityBranch(): void
    {
        $branch = new ActivityBranch('', 'пр. Океанский, 44');
        $branch->addSlot(new ScheduleSlot(DayOfWeek::Tuesday, '15:30', '17:30'));

        self::assertSame(Branch::DEFAULT_NAME, $branch->getName());
        self::assertSame('Вт · 15:30–17:30', $this->formatter->formatBranch($branch));
    }

    public function testDaysAreUniqueAndOrdered(): void
    {
        $slots = [
            new ScheduleSlot(DayOfWeek::Sunday, '09:00', '10:00'),
            new ScheduleSlot(DayOfWeek::Monday, '09:00', '10:00'),
            new ScheduleSlot(DayOfWeek::Monday, '12:00', '13:00'),
        ];

        self::assertSame([DayOfWeek::Monday, DayOfWeek::Sunday], ScheduleSlot::days($slots));
    }
}
