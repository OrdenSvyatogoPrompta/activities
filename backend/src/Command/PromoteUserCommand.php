<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Единственный способ выдать ROLE_ADMIN на проде. Роль задаётся опцией явно,
 * чтобы запуск без опций ничего не менял.
 */
#[AsCommand(name: 'app:user:promote', description: 'Выдаёт пользователю роль (--admin — администратор)')]
final readonly class PromoteUserCommand
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email пользователя')]
        string $email,
        #[Option(description: 'Выдать роль администратора (ROLE_ADMIN)')]
        bool $admin = false,
    ): int {
        if (!$admin) {
            $io->error('Не указана роль. Доступные опции: --admin');

            return Command::INVALID;
        }

        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(\sprintf('Пользователь %s не найден', $email));

            return Command::FAILURE;
        }

        if ($user->isAdmin()) {
            $io->note(\sprintf('Пользователь %s уже администратор', $user->getEmail()));

            return Command::SUCCESS;
        }

        $user->promoteToAdmin();
        $this->entityManager->flush();

        $io->success(\sprintf('Пользователь %s получил роль ROLE_ADMIN', $user->getEmail()));

        return Command::SUCCESS;
    }
}
