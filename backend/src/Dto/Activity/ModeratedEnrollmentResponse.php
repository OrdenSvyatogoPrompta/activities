<?php

declare(strict_types=1);

namespace App\Dto\Activity;

/**
 * Заявка в ответе `POST /api/enrollments/{id}/confirm|reject` — формат `Enrollment`
 * группы F (раздел 4, «Группа F»).
 *
 * Дубль EnrollmentResponse группы F на время параллельной разработки: при слиянии веток
 * заменить на него (формат JSON совпадает).
 */
final readonly class ModeratedEnrollmentResponse
{
    /**
     * @param list<string> $days
     */
    public function __construct(
        public string $id,
        public string $clubId,
        public string $clubName,
        public ?string $branchId,
        public ?string $branchName,
        public string $schedule,
        public array $days,
        public string $color,
        public ?EnrollmentChildResponse $forChild,
        public string $enrolledAt,
        public string $status,
        public ?string $rejectReason,
    ) {
    }
}
