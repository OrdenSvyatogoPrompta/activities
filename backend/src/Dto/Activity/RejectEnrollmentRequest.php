<?php

declare(strict_types=1);

namespace App\Dto\Activity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Тело `POST /api/enrollments/{id}/reject`: `{ reason? }`.
 */
final readonly class RejectEnrollmentRequest
{
    public function __construct(
        #[Assert\Length(max: 500, maxMessage: 'Причина отклонения — не длиннее {{ limit }} символов')]
        public ?string $reason = null,
    ) {
    }
}
