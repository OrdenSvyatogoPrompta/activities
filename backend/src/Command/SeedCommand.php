<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\Seed\DemoData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Наполняет пустую базу тем же набором, что фикстуры. Если пользователи уже есть,
 * ничего не делает: в отличие от doctrine:fixtures:load, данные не стираются.
 */
#[AsCommand(name: 'app:seed', description: 'Наполняет пустую базу демо-данными (запускается при деплое)')]
final readonly class SeedCommand
{
    public function __construct(
        private UserRepository $users,
        private DemoData $demoData,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if ($this->users->count() > 0) {
            $io->note('В базе уже есть данные, наполнение пропущено');

            return Command::SUCCESS;
        }

        $this->demoData->load($this->entityManager);
        $io->success('База наполнена демо-данными');

        return Command::SUCCESS;
    }
}
