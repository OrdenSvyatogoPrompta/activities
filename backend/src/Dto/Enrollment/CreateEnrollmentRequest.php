<?php

declare(strict_types=1);

namespace App\Dto\Enrollment;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Тело `POST /api/enrollments`: `{ clubId (slug), branchId?, childId? }`.
 *
 * - `branchId` можно не передавать, если у кружка один филиал — сервер подставит его;
 * - `childId` не передан — заявка «для себя».
 *
 * Принадлежность филиала кружку и ребёнка автору проверяет EnrollmentService (→ 422).
 */
final readonly class CreateEnrollmentRequest
{
    public function __construct(
        // Значение по умолчанию — чтобы отсутствующее поле дало понятное русское
        // сообщение NotBlank, а не ошибку денормализации
        #[Assert\NotBlank(message: 'Укажите кружок')]
        #[Assert\Length(max: 255, maxMessage: 'Идентификатор кружка не длиннее {{ limit }} символов')]
        public string $clubId = '',

        #[Assert\Uuid(message: 'Некорректный идентификатор филиала')]
        public ?string $branchId = null,

        #[Assert\Uuid(message: 'Некорректный идентификатор ребёнка')]
        public ?string $childId = null,
    ) {
    }
}
