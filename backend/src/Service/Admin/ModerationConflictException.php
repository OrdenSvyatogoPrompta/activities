<?php

declare(strict_types=1);

namespace App\Service\Admin;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class ModerationConflictException extends \RuntimeException
{
    public static function notPending(): self
    {
        return new self('Модерировать можно только активность на рассмотрении');
    }

    public static function noBranches(): self
    {
        return new self('У активности нет филиалов — опубликовать её в каталоге нельзя');
    }
}
