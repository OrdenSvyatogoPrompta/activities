<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\Enum\ActivityStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ActivityListQuery
{
    public function __construct(
        #[Assert\Choice(callback: [ActivityStatus::class, 'values'], message: 'Недопустимый статус активности: допустимы pending, approved, rejected')]
        public string $status = ActivityStatus::Pending->value,
    ) {
    }

    public function statusEnum(): ActivityStatus
    {
        return ActivityStatus::from($this->status);
    }
}
