<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Club;
use App\Entity\Enrollment;
use App\Entity\Enum\Category;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Entity\User;
use App\Security\Voter\EnrollmentVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class EnrollmentVoterTest extends TestCase
{
    private User $owner;
    private User $author;
    private User $stranger;
    private User $admin;

    protected function setUp(): void
    {
        $this->owner = new User('owner@example.com', 'Владелец');
        $this->author = new User('author@example.com', 'Автор');
        $this->stranger = new User('stranger@example.com', 'Посторонний');
        $this->admin = (new User('admin@example.com', 'Админ'))->promoteToAdmin();
    }

    /**
     * @return iterable<string, array{string, bool, string, int}>
     */
    public static function cases(): iterable
    {
        // [кто голосует, у кружка есть владелец, атрибут, ожидаемый результат]
        yield 'владелец подтверждает' => ['owner', true, EnrollmentVoter::MODERATE, VoterInterface::ACCESS_GRANTED];
        yield 'админ подтверждает в чужом кружке' => ['admin', true, EnrollmentVoter::MODERATE, VoterInterface::ACCESS_GRANTED];
        yield 'админ подтверждает в бесхозном кружке' => ['admin', false, EnrollmentVoter::MODERATE, VoterInterface::ACCESS_GRANTED];
        yield 'автор не подтверждает свою заявку' => ['author', true, EnrollmentVoter::MODERATE, VoterInterface::ACCESS_DENIED];
        yield 'посторонний не подтверждает' => ['stranger', true, EnrollmentVoter::MODERATE, VoterInterface::ACCESS_DENIED];
        yield 'в бесхозном кружке подтверждает только админ' => ['stranger', false, EnrollmentVoter::MODERATE, VoterInterface::ACCESS_DENIED];
        yield 'автор отменяет' => ['author', true, EnrollmentVoter::CANCEL, VoterInterface::ACCESS_GRANTED];
        yield 'владелец не отменяет' => ['owner', true, EnrollmentVoter::CANCEL, VoterInterface::ACCESS_DENIED];
        yield 'админ не отменяет' => ['admin', true, EnrollmentVoter::CANCEL, VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('cases')]
    public function testVote(string $voter, bool $clubHasOwner, string $attribute, int $expected): void
    {
        $enrollment = new Enrollment($this->author, $this->club($clubHasOwner ? $this->owner : null), null);
        $user = $this->{$voter};
        \assert($user instanceof User);

        $token = new UsernamePasswordToken($user, 'api', $user->getRoles());

        self::assertSame($expected, (new EnrollmentVoter())->vote($token, $enrollment, [$attribute]));
    }

    public function testAnonymousIsDenied(): void
    {
        $enrollment = new Enrollment($this->author, $this->club($this->owner), null);

        self::assertSame(VoterInterface::ACCESS_DENIED, (new EnrollmentVoter())->vote(new NullToken(), $enrollment, [EnrollmentVoter::MODERATE]));
    }

    public function testAbstainsOnForeignAttributeOrSubject(): void
    {
        $voter = new EnrollmentVoter();
        $token = new UsernamePasswordToken($this->admin, 'api', $this->admin->getRoles());
        $enrollment = new Enrollment($this->author, $this->club($this->owner), null);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $enrollment, ['ROLE_ADMIN']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), [EnrollmentVoter::MODERATE]));
    }

    private function club(?User $owner): Club
    {
        return new Club(
            slug: 'akvarel',
            name: 'Акварель',
            categories: [Category::Creativity],
            emoji: '🎨',
            color: '#f59e0b',
            shortDescription: 'Коротко',
            fullDescription: 'Полно',
            ageMin: 5,
            ageMax: 14,
            visitType: VisitType::Both,
            priceType: PriceType::Pushkin,
            priceAmount: 2500,
            owner: $owner,
        );
    }
}
