<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Activity;
use App\Entity\ActivityBranch;
use App\Entity\Branch;
use App\Entity\Child;
use App\Entity\Club;
use App\Entity\Enrollment;
use App\Entity\Enum\ActivityStatus;
use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\EnrollmentStatus;
use App\Entity\Enum\Gender;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Entity\ScheduleSlot;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Инварианты сущностей, которые не требуют БД.
 */
final class DomainModelTest extends TestCase
{
    public function testUserEmailIsNormalizedAndRoleUserAlwaysPresent(): void
    {
        $user = new User('  Maria@Example.COM ', 'Иванова Мария');

        self::assertSame('maria@example.com', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertFalse($user->isAdmin());

        $user->promoteToAdmin();
        self::assertTrue($user->isAdmin());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testSlotRequiresTimeFromBeforeTimeTo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ScheduleSlot(DayOfWeek::Monday, '18:00', '15:00');
    }

    public function testSlotRejectsMalformedTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ScheduleSlot(DayOfWeek::Monday, '9:00', '15:00');
    }

    public function testClubDaysAreUnionOfBranchDays(): void
    {
        $club = $this->club();
        $first = new Branch('Борисенко', 'ул. Борисенко, 100');
        $first->addSlot(new ScheduleSlot(DayOfWeek::Wednesday, '15:00', '18:00'));
        $first->addSlot(new ScheduleSlot(DayOfWeek::Monday, '15:00', '18:00'));
        $second = new Branch('Снеговая', 'ул. Снеговая, 42б');
        $second->addSlot(new ScheduleSlot(DayOfWeek::Saturday, '09:00', '12:00'));
        $second->addSlot(new ScheduleSlot(DayOfWeek::Monday, '09:00', '12:00'));
        $club->addBranch($first)->addBranch($second);

        self::assertSame([DayOfWeek::Monday, DayOfWeek::Wednesday, DayOfWeek::Saturday], $club->getDays());
        self::assertSame(0, $first->getPosition());
        self::assertSame(1, $second->getPosition());
        self::assertNull($club->getSingleBranch());
        self::assertSame($second, $club->findBranch((string) $second->getId()));
        self::assertNull($club->findBranch('not-a-uuid'));
    }

    public function testFreeClubHasZeroPrice(): void
    {
        $club = $this->club();
        $club->setPrice(PriceType::Free, 1500);

        self::assertSame(0, $club->getPriceAmount());
    }

    public function testEnrollmentRejectsForeignChildAndBranch(): void
    {
        $user = new User('maria@example.com', 'Мария');
        $stranger = new User('anna@example.com', 'Анна');
        $club = $this->club();
        $club->addBranch(new Branch(Branch::DEFAULT_NAME, 'адрес'));

        $this->expectException(\InvalidArgumentException::class);

        new Enrollment($user, $club, $club->getSingleBranch(), new Child($stranger, 'Маша', null, 7, Gender::Female));
    }

    public function testEnrollmentMarkingAndPermissions(): void
    {
        $owner = new User('owner@example.com', 'Владелец');
        $author = new User('maria@example.com', 'Мария');
        $admin = (new User('admin@example.com', 'Админ'))->promoteToAdmin();
        $club = $this->club($owner);
        $club->addBranch(new Branch(Branch::DEFAULT_NAME, 'адрес'));

        $enrollment = new Enrollment($author, $club, $club->getSingleBranch());

        self::assertSame(EnrollmentStatus::Pending, $enrollment->getStatus());
        self::assertSame('pending', $enrollment->getMarking());
        self::assertNull($enrollment->getChild());
        self::assertTrue($enrollment->canBeModeratedBy($owner));
        self::assertFalse($enrollment->canBeModeratedBy($admin));
        self::assertFalse($enrollment->canBeModeratedBy($author));
        self::assertTrue($enrollment->canBeCancelledBy($author));
        self::assertFalse($enrollment->canBeCancelledBy($owner));

        $enrollment->setMarking('cancelled');
        self::assertSame(EnrollmentStatus::Cancelled, $enrollment->getStatus());
    }

    public function testOwnerlessClubEnrollmentIsModeratedByNobody(): void
    {
        $club = $this->club();
        $club->addBranch(new Branch(Branch::DEFAULT_NAME, 'адрес'));
        $user = new User('maria@example.com', 'Мария');
        $admin = (new User('admin@example.com', 'Админ'))->promoteToAdmin();
        $enrollment = new Enrollment($user, $club, null);

        self::assertFalse($enrollment->canBeModeratedBy($user));
        self::assertFalse($enrollment->canBeModeratedBy($admin));
    }

    public function testActivityApproval(): void
    {
        $owner = new User('maria@example.com', 'Мария');
        $admin = (new User('admin@example.com', 'Админ'))->promoteToAdmin();
        $activity = new Activity($owner, 'Акварель', Category::Creativity, 'Описание.', 5, 14, VisitType::Both, PriceType::Pushkin, 2500);
        $source = new ActivityBranch('Основной', 'пр. Океанский, 44');
        $source->addSlot(new ScheduleSlot(DayOfWeek::Tuesday, '15:30', '17:30'));
        $activity->addBranch($source);

        self::assertSame(Activity::DEFAULT_EMOJI, $activity->getEmoji());

        $club = $this->club($owner, $activity);
        $branch = Branch::fromActivityBranch($source);
        $club->addBranch($branch);
        $activity->approve($club, $admin);

        self::assertSame(ActivityStatus::Approved, $activity->getStatus());
        self::assertSame($club, $activity->getClub());
        self::assertSame($admin, $activity->getModeratedBy());
        self::assertSame($source, $branch->getSourceActivityBranch());
        self::assertSame([DayOfWeek::Tuesday], $branch->getDays());
        self::assertNotSame($source->getSlots()[0], $branch->getSlots()[0]);

        $this->expectException(\LogicException::class);
        $activity->reject('Повторно', $admin);
    }

    private function club(?User $owner = null, ?Activity $sourceActivity = null): Club
    {
        return new Club(
            slug: 'swimming-dyush',
            name: 'Плавание · ДЮСШ №1',
            categories: [Category::Sport],
            emoji: '🏊',
            color: '#0ea5e9',
            shortDescription: 'Коротко',
            fullDescription: 'Полно',
            ageMin: 6,
            ageMax: 18,
            visitType: VisitType::Recurring,
            priceType: PriceType::Paid,
            priceAmount: 1000,
            owner: $owner,
            sourceActivity: $sourceActivity,
        );
    }
}
