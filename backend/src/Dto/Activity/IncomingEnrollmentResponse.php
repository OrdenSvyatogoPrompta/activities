<?php

declare(strict_types=1);

namespace App\Dto\Activity;

/**
 * Входящая заявка в кружок активности (совместима с `IncomingEnrollment` фронтенда).
 *
 * Организатор видит только имя заявителя и имя ребёнка (контакты — открытый вопрос).
 * `branchId` — филиал кружка; `activityBranchId` — филиал активности, из которого он создан
 * при одобрении (Branch::getSourceActivityBranch()), чтобы показать заявку под филиалом активности.
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
