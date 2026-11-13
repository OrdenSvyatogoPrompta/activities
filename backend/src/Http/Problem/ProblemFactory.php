<?php

declare(strict_types=1);

namespace App\Http\Problem;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * `detail` берётся из сообщения исключения, только если оно на русском (написано в коде
 * приложения); английские сообщения фреймворка и текст 5xx заменяются стандартными.
 */
final readonly class ProblemFactory
{
    /**
     * Сообщения денормализации MapRequestPayload: переводчик не установлен.
     */
    private const array VIOLATION_TEMPLATES = [
        'This value should be of type {{ type }}.' => 'Значение должно иметь тип {{ type }}.',
        'This value was of an unexpected type.' => 'Значение имеет недопустимый тип.',
        'This attribute was not expected.' => 'Неизвестное поле.',
    ];

    private const array DEFAULT_DETAILS = [
        Response::HTTP_BAD_REQUEST => 'Некорректный JSON или параметры запроса',
        Response::HTTP_UNAUTHORIZED => 'Требуется аутентификация',
        Response::HTTP_FORBIDDEN => 'Недостаточно прав для выполнения действия',
        Response::HTTP_NOT_FOUND => 'Запрошенный ресурс не найден',
        Response::HTTP_METHOD_NOT_ALLOWED => 'Метод не поддерживается для этого адреса',
        Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Ожидается тело запроса в формате JSON',
        Response::HTTP_UNPROCESSABLE_ENTITY => 'Данные запроса не прошли проверку',
        Response::HTTP_TOO_MANY_REQUESTS => 'Слишком много запросов, попробуйте позже',
    ];

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function fromThrowable(\Throwable $throwable): ProblemResponse
    {
        [$status, $headers] = $this->statusAndHeaders($throwable);

        $extra = [];
        $violations = $this->findViolations($throwable);
        if (null !== $violations) {
            $extra['violations'] = $violations;
        }

        if ($this->debug) {
            $extra['debug'] = [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile().':'.$throwable->getLine(),
            ];
        }

        // Сообщение исключения валидации — склейка нарушений, они и так в violations
        $detail = null !== $violations ? self::DEFAULT_DETAILS[Response::HTTP_UNPROCESSABLE_ENTITY] : $this->detail($throwable, $status);

        return new ProblemResponse($status, $detail, null, $extra, $headers);
    }

    public function authenticationProblem(string $detail, int $status = Response::HTTP_UNAUTHORIZED): ProblemResponse
    {
        $headers = Response::HTTP_UNAUTHORIZED === $status ? ['WWW-Authenticate' => 'Bearer'] : [];

        return new ProblemResponse($status, $detail, null, [], $headers);
    }

    /**
     * @return array{int, array<string, string>}
     */
    private function statusAndHeaders(\Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof HttpExceptionInterface => [$throwable->getStatusCode(), $throwable->getHeaders()],
            $throwable instanceof AuthenticationException => [Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']],
            $throwable instanceof AccessDeniedException => [Response::HTTP_FORBIDDEN, []],
            default => [Response::HTTP_INTERNAL_SERVER_ERROR, []],
        };
    }

    private function detail(\Throwable $throwable, int $status): string
    {
        $message = trim($throwable->getMessage());

        if ($status < 500 && '' !== $message && preg_match('/\p{Cyrillic}/u', $message)) {
            return $message;
        }

        $previous = $throwable->getPrevious();

        return match (true) {
            $previous instanceof ResourceNotFoundException => 'Маршрут не найден',
            $previous instanceof MethodNotAllowedException => 'Метод не поддерживается для этого адреса',
            $previous instanceof NotEncodableValueException => 'Некорректный JSON в теле запроса',
            default => self::DEFAULT_DETAILS[$status] ?? ProblemResponse::titleFor($status),
        };
    }

    /**
     * @return list<array{propertyPath: string, message: string}>|null
     */
    private function findViolations(\Throwable $throwable): ?array
    {
        for ($e = $throwable; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof ValidationFailedException) {
                $violations = [];
                foreach ($e->getViolations() as $violation) {
                    $violations[] = [
                        'propertyPath' => $violation->getPropertyPath(),
                        'message' => $this->violationMessage($violation),
                    ];
                }

                return $violations;
            }
        }

        return null;
    }

    private function violationMessage(ConstraintViolationInterface $violation): string
    {
        $template = self::VIOLATION_TEMPLATES[$violation->getMessageTemplate()] ?? null;
        if (null === $template) {
            return (string) $violation->getMessage();
        }

        $parameters = array_filter(
            $violation->getParameters(),
            static fn (int|string $key): bool => \is_string($key) && str_starts_with($key, '{{'),
            \ARRAY_FILTER_USE_KEY,
        );

        return strtr($template, $parameters);
    }
}
