<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Club;
use App\Entity\User;
use App\Service\Seed\DemoData;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedCommandTest extends KernelTestCase
{
    public function testSkipsWhenDataExists(): void
    {
        $entityManager = $this->entityManager();
        $clubsBefore = $entityManager->getRepository(Club::class)->count();

        $tester = $this->executeSeed();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('наполнение пропущено', $tester->getDisplay());
        self::assertSame($clubsBefore, $entityManager->getRepository(Club::class)->count());
    }

    public function testSeedsEmptyDatabase(): void
    {
        $entityManager = $this->entityManager();
        (new ORMPurger($entityManager))->purge();

        $tester = $this->executeSeed();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNotNull($entityManager->getRepository(User::class)->findOneBy(['email' => DemoData::DEMO_EMAIL]));
        self::assertTrue($entityManager->getRepository(User::class)->findOneBy(['email' => DemoData::ADMIN_EMAIL])?->isAdmin());
        self::assertNotNull($entityManager->getRepository(Club::class)->findOneBy(['slug' => 'akvarel']));
    }

    private function executeSeed(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:seed'));
        $tester->execute([]);

        return $tester;
    }

    private function entityManager(): EntityManagerInterface
    {
        self::bootKernel();

        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
