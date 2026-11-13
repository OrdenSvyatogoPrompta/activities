<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Dto\Auth\RegisterRequest;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
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

    /**
     * Создаёт пользователя. Занятый email (без учёта регистра) → 422 с нарушением по полю `email`.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function register(RegisterRequest $request): User
    {
        // Конструктор нормализует email (trim + нижний регистр)
        $user = new User($request->email, trim($request->name));
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));

        // Правила сущности, в т. ч. UniqueEntity по email
        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            throw $this->validationError($user, $violations);
        }

        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Гонка двух одновременных регистраций: проверку выше прошли оба запроса
            throw $this->validationError($user, new ConstraintViolationList([
                new ConstraintViolation(self::EMAIL_TAKEN, self::EMAIL_TAKEN, [], $user, 'email', $user->getEmail(), cause: $e),
            ]));
        }

        return $user;
    }

    /**
     * Та же форма, что у ошибок `#[MapRequestPayload]`: ProblemFactory найдёт нарушения в цепочке.
     */
    private function validationError(User $user, ConstraintViolationListInterface $violations): UnprocessableEntityHttpException
    {
        return new UnprocessableEntityHttpException('Ошибка валидации', new ValidationFailedException($user, $violations));
    }
}
