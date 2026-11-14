<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\Enum\EnrollmentStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class EnrollmentListQuery
{
    public function __construct(
        #[Assert\Choice(callback: [EnrollmentStatus::class, 'values'], message: 'Недопустимый статус заявки: допустимы pending, confirmed, rejected, cancelled')]
        public string $status = EnrollmentStatus::Pending->value,
        /** Только кружки без владельца — их заявки подтверждает администратор. */
        public bool $ownerless = false,
    ) {
    }

    public function statusEnum(): EnrollmentStatus
    {
        return EnrollmentStatus::from($this->status);
    }
}
