<?php

declare(strict_types=1);

namespace App\Dto\Enrollment;

use App\Entity\Enrollment;
use App\Entity\Enum\DayOfWeek;
use App\Service\Formatter\ScheduleFormatter;

/**
 * Заявка на запись в ответах API — единый формат для «моих заявок» (группа F),
 * подтверждения/отклонения (группа G) и админки (группа H).
 *
 * Совместим с `Enrollment` из frontend/apps/main/src/app/services/auth.service.ts,
 * плюс `status` и `rejectReason`:
 * ```json
 * { "id": "…", "clubId": "swimming-dyush", "clubName": "Плавание · ДЮСШ №1",
 *   "branchId": "…", "branchName": "Борисенко", "schedule": "Пн, Ср, Пт · 15:00–18:00",
 *   "days": ["Пн", "Ср", "Пт"], "color": "#0ea5e9", "forChild": { … } | null,
 *   "enrolledAt": "2026-10-15", "status": "pending", "rejectReason": null }
 * ```
 *
 * `branchId`/`branchName` равны null, только если филиал удалили после подачи заявки
 * (FK `ON DELETE SET NULL`); тогда `schedule` — пустая строка, `days` — пустой список.
 */
final readonly class EnrollmentResponse
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

    /**
     * Сущность должна быть загружена со связями (EnrollmentRepository::findOneWithDetails(),
     * findByUser() и т. п.), иначе на каждый филиал будет отдельный запрос слотов.
     */
    public static function fromEntity(Enrollment $enrollment, ScheduleFormatter $scheduleFormatter): self
    {
        $club = $enrollment->getClub();
        $branch = $enrollment->getBranch();
        $child = $enrollment->getChild();

        return new self(
            id: $enrollment->getId()->toRfc4122(),
            clubId: $club->getSlug(),
            clubName: $club->getName(),
            branchId: $branch?->getId()->toRfc4122(),
            branchName: $branch?->getName(),
            schedule: null === $branch ? '' : $scheduleFormatter->formatBranch($branch),
            days: null === $branch ? [] : array_map(
                static fn (DayOfWeek $day): string => $day->value,
                $scheduleFormatter->days($branch->getSlots()),
            ),
            color: $club->getColor(),
            forChild: null === $child ? null : EnrollmentChildResponse::fromEntity($child),
            enrolledAt: $enrollment->getCreatedAt()->format('Y-m-d'),
            status: $enrollment->getStatus()->value,
            rejectReason: $enrollment->getRejectReason(),
        );
    }

    /**
     * @param iterable<Enrollment> $enrollments
     *
     * @return list<self>
     */
    public static function fromEntities(iterable $enrollments, ScheduleFormatter $scheduleFormatter): array
    {
        $result = [];
        foreach ($enrollments as $enrollment) {
            $result[] = self::fromEntity($enrollment, $scheduleFormatter);
        }

        return $result;
    }
}
