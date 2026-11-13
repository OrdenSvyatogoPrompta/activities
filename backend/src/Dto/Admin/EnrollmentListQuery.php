<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\Enum\EnrollmentStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query-параметры `GET /api/admin/enrollments`: `?status=pending&ownerless=true`.
 * По умолчанию — заявки на рассмотрении во все кружки.
 */
final readonly class EnrollmentListQuery
{
    public function __construct(
        #[Assert\Choice(callback: [self::class, 'statuses'], message: 'Недопустимый статус заявки: допустимы pending, confirmed, rejected, cancelled')]
        public string $status = EnrollmentStatus::Pending->value,
        /** Только заявки в кружки без владельца (их подтверждает администратор). */
        public bool $ownerless = false,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_map(static fn (EnrollmentStatus $status): string => $status->value, EnrollmentStatus::cases());
    }

    public function statusEnum(): EnrollmentStatus
    {
        return EnrollmentStatus::from($this->status);
    }
}
