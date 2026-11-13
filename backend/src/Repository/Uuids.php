<?php

declare(strict_types=1);

namespace App\Repository;

use Symfony\Component\Uid\Uuid;

/**
 * Разбор id из URL/тела запроса: некорректная строка — null (→ 404/422), а не исключение.
 *
 * @internal
 */
final class Uuids
{
    public static function parse(Uuid|string|null $id): ?Uuid
    {
        if (null === $id || $id instanceof Uuid) {
            return $id;
        }

        return Uuid::isValid($id) ? Uuid::fromString($id) : null;
    }
}
