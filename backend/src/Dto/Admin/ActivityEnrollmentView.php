<?php

declare(strict_types=1);

namespace App\Dto\Admin;

/**
 * Входящая заявка в кружок одобренной активности (`Activity.enrollments[]`, контракт группы G).
 * `branchId` — id филиала кружка.
 */
final readonly class ActivityEnrollmentView
{
    public function __construct(
        public string $id,
        public string $personName,
        public ?string $childName,
        public ?string $branchId,
        public ?string $branchName,
        public string $enrolledAt,
        public string $status,
    ) {
    }
}
