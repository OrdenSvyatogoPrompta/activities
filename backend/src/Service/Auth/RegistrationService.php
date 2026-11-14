<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Dto\Auth\RegisterRequest;
use App\Entity\User;
use App\Http\Problem\ValidationProblem;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class RegistrationService
{
    private const string EMAIL_TAKEN = 'Email уже занят';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function register(RegisterRequest $request): User
    {
        $user = new User($request->email, trim($request->name));
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            throw ValidationProblem::fromViolations($user, $violations);
        }

        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Гонка двух одновременных регистраций: UniqueEntity прошли оба запроса
            throw ValidationProblem::forProperty($user, 'email', $user->getEmail(), self::EMAIL_TAKEN, $e);
        }

        return $user;
    }
}
