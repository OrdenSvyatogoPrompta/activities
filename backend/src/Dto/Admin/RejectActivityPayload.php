<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Тело `POST /api/admin/activities/{id}/reject`: `{ comment }` — причина отклонения,
 * её увидит автор активности.
 */
final readonly class RejectActivityPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите причину отклонения', normalizer: 'trim')]
        #[Assert\Length(max: 1000, maxMessage: 'Комментарий не длиннее {{ limit }} символов')]
        public string $comment = '',
    ) {
    }
}
