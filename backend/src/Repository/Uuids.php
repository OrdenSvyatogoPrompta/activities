<?php

declare(strict_types=1);

namespace App\Repository;

use Symfony\Component\Uid\Uuid;

/**
 * Некорректный id из запроса — null (→ 404/422), а не исключение.
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
