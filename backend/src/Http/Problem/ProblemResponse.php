<?php

declare(strict_types=1);

namespace App\Http\Problem;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 7807.
 */
final class ProblemResponse extends JsonResponse
{
    public const string CONTENT_TYPE = 'application/problem+json';

    private const array TITLES = [
        Response::HTTP_BAD_REQUEST => 'Некорректный запрос',
        Response::HTTP_UNAUTHORIZED => 'Требуется аутентификация',
        Response::HTTP_FORBIDDEN => 'Доступ запрещён',
        Response::HTTP_NOT_FOUND => 'Не найдено',
        Response::HTTP_METHOD_NOT_ALLOWED => 'Метод не поддерживается',
        Response::HTTP_NOT_ACCEPTABLE => 'Формат ответа не поддерживается',
        Response::HTTP_CONFLICT => 'Конфликт',
        Response::HTTP_GONE => 'Ресурс удалён',
        Response::HTTP_PRECONDITION_FAILED => 'Условие запроса не выполнено',
        Response::HTTP_REQUEST_ENTITY_TOO_LARGE => 'Слишком большой запрос',
        Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Формат запроса не поддерживается',
        Response::HTTP_UNPROCESSABLE_ENTITY => 'Ошибка валидации',
        Response::HTTP_TOO_MANY_REQUESTS => 'Слишком много запросов',
        Response::HTTP_INTERNAL_SERVER_ERROR => 'Внутренняя ошибка сервера',
        Response::HTTP_SERVICE_UNAVAILABLE => 'Сервис недоступен',
    ];

    /**
     * @param array<string, mixed>                    $extra
     * @param array<string, string|list<string>|null> $headers
     */
    public function __construct(int $status, ?string $detail = null, ?string $title = null, array $extra = [], array $headers = [])
    {
        $this->encodingOptions = self::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

        $data = [
            'type' => 'about:blank',
            'title' => $title ?? self::titleFor($status),
            'status' => $status,
            'detail' => $detail ?? $title ?? self::titleFor($status),
        ];

        parent::__construct($data + $extra, $status, [...$headers, 'Content-Type' => self::CONTENT_TYPE]);
    }

    public static function titleFor(int $status): string
    {
        return self::TITLES[$status]
            ?? ($status >= 500 ? self::TITLES[Response::HTTP_INTERNAL_SERVER_ERROR] : self::TITLES[Response::HTTP_BAD_REQUEST]);
    }
}
