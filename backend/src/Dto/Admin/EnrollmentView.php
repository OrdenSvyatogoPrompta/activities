<?php

declare(strict_types=1);

namespace App\Dto\Admin;

/**
 * Заявка для администратора: формат `Enrollment` (контракт группы F) + `club` и заявитель `user`.
 */
final readonly class EnrollmentView
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
        public ?string $schedule,
        public array $days,
        public string $color,
        public ?ChildView $forChild,
        public string $enrolledAt,
        public string $status,
        public ?string $rejectReason,
        public ClubRefView $club,
        public UserRefView $user,
    ) {
    }
}
