<?php

declare(strict_types=1);

namespace App\Dto\Activity;

/**
 * `branchId` — филиал кружка, `activityBranchId` — филиал активности, из которого он создан.
 */
final readonly class IncomingEnrollmentResponse
{
    public function __construct(
        public string $id,
        public string $personName,
        public ?string $childName,
        public ?string $branchId,
        public ?string $branchName,
        public ?string $activityBranchId,
        public string $enrolledAt,
        public string $status,
    ) {
    }
}
