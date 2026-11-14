<?php

declare(strict_types=1);

namespace App\Http\Problem;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * 422 той же формы, что у `#[MapRequestPayload]`: ProblemFactory найдёт нарушения в цепочке.
 */
final class ValidationProblem
{
    public static function fromViolations(object $root, ConstraintViolationListInterface $violations): UnprocessableEntityHttpException
    {
        return new UnprocessableEntityHttpException('Ошибка валидации', new ValidationFailedException($root, $violations));
    }

    public static function forProperty(object $root, string $propertyPath, mixed $invalidValue, string $message, ?\Throwable $cause = null): UnprocessableEntityHttpException
    {
        return self::fromViolations($root, new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], $root, $propertyPath, $invalidValue, cause: $cause),
        ]));
    }
}
