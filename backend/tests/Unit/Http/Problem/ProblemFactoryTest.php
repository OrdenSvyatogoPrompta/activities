<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Problem;

use App\Http\Problem\ProblemFactory;
use App\Http\Problem\ProblemResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ProblemFactoryTest extends TestCase
{
    public function testValidationFailureBecomes422WithViolations(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Email уже занят', 'Email уже занят', [], null, 'email', 'maria@example.com'),
            new ConstraintViolation(
                'This value should be of type int.',
                'This value should be of type {{ type }}.',
                ['{{ type }}' => 'int', 'hint' => 'Подсказка'],
                null,
                'age',
                'abc',
            ),
        ]);
        $exception = HttpException::fromStatusCode(422, 'Email уже занят', new ValidationFailedException(null, $violations));

        $problem = $this->decode((new ProblemFactory(false))->fromThrowable($exception));

        self::assertSame([
            'type' => 'about:blank',
            'title' => 'Ошибка валидации',
            'status' => 422,
            'detail' => 'Данные запроса не прошли проверку',
            'violations' => [
                ['propertyPath' => 'email', 'message' => 'Email уже занят'],
                ['propertyPath' => 'age', 'message' => 'Значение должно иметь тип int.'],
            ],
        ], $problem);
    }

    public function testRussianMessageIsPassedAsDetail(): void
    {
        $response = (new ProblemFactory(false))->fromThrowable(new ConflictHttpException('Заявка уже существует'));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(ProblemResponse::CONTENT_TYPE, $response->headers->get('Content-Type'));
        self::assertSame('Заявка уже существует', $this->decode($response)['detail']);
    }

    public function testFrameworkMessageIsReplacedWithRussianDefault(): void
    {
        $problem = $this->decode((new ProblemFactory(false))->fromThrowable(
            new NotFoundHttpException('"App\Entity\Club" object not found by "EntityValueResolver".'),
        ));

        self::assertSame(404, $problem['status']);
        self::assertSame('Запрошенный ресурс не найден', $problem['detail']);
    }

    public function testServerErrorHidesMessage(): void
    {
        $problem = $this->decode((new ProblemFactory(false))->fromThrowable(new \RuntimeException('SQLSTATE[HY000] секрет')));

        self::assertSame(500, $problem['status']);
        self::assertSame('Внутренняя ошибка сервера', $problem['detail']);
        self::assertArrayNotHasKey('debug', $problem);
    }

    public function testDebugModeExposesOriginalException(): void
    {
        $problem = $this->decode((new ProblemFactory(true))->fromThrowable(new \RuntimeException('boom')));

        self::assertSame(\RuntimeException::class, $problem['debug']['class']);
        self::assertSame('boom', $problem['debug']['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ProblemResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
