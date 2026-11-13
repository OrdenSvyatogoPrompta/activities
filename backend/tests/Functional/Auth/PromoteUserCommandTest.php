<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PromoteUserCommandTest extends KernelTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());
        $this->tester = new CommandTester($application->find('app:user:promote'));
    }

    public function testPromotesUserToAdmin(): void
    {
        $email = $this->createUser();

        $this->tester->execute(['email' => strtoupper($email), '--admin' => true]);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertStringContainsString('ROLE_ADMIN', $this->tester->getDisplay());
        self::assertTrue($this->reload($email)->isAdmin());
    }

    public function testPromotingAdminAgainIsNoop(): void
    {
        $email = $this->createUser(admin: true);

        $this->tester->execute(['email' => $email, '--admin' => true]);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertStringContainsString('уже администратор', $this->tester->getDisplay());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->reload($email)->getRoles());
    }

    public function testUnknownUserFails(): void
    {
        $this->tester->execute(['email' => 'nobody-'.bin2hex(random_bytes(4)).'@example.com', '--admin' => true]);

        self::assertSame(Command::FAILURE, $this->tester->getStatusCode());
        self::assertStringContainsString('не найден', $this->tester->getDisplay());
    }

    public function testRoleOptionIsRequired(): void
    {
        $email = $this->createUser();

        $this->tester->execute(['email' => $email]);

        self::assertSame(Command::INVALID, $this->tester->getStatusCode());
        self::assertFalse($this->reload($email)->isAdmin());
    }

    private function createUser(bool $admin = false): string
    {
        $email = 'promote-'.bin2hex(random_bytes(6)).'@example.com';
        $user = new User($email, 'Кандидат');
        $user->setPassword('not-a-real-hash');
        if ($admin) {
            $user->promoteToAdmin();
        }

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $email;
    }

    private function reload(string $email): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
