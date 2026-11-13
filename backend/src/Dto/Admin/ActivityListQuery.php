<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\Enum\ActivityStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query-параметры `GET /api/admin/activities`: `?status=pending|approved|rejected`
 * (по умолчанию — очередь модерации, pending).
 */
final readonly class ActivityListQuery
{
    public function __construct(
        #[Assert\Choice(callback: [self::class, 'statuses'], message: 'Недопустимый статус активности: допустимы pending, approved, rejected')]
        public string $status = ActivityStatus::Pending->value,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_map(static fn (ActivityStatus $status): string => $status->value, ActivityStatus::cases());
    }

    public function statusEnum(): ActivityStatus
    {
        return ActivityStatus::from($this->status);
    }
}
