<?php

declare(strict_types=1);

namespace App\Dto\Activity;

/**
 * `activityBranchId` — филиал активности, из которого создан филиал кружка заявки.
 */
final readonly class IncomingEnrollmentResponse
{
    public function __construct(
        public string $id,
        public string $personName,
        public ?string $childName,
        public ?string $branchName,
        public ?string $activityBranchId,
        public string $enrolledAt,
        public string $status,
    ) {
    }
}
