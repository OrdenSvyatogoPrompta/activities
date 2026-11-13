<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Dto\Activity\ActivityResponse;

/**
 * Формат активности организатора плюс `owner`.
 */
final readonly class ActivityView implements \JsonSerializable
{
    public function __construct(
        public ActivityResponse $activity,
        public UserRefView $owner,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...get_object_vars($this->activity), 'owner' => $this->owner];
    }
}
