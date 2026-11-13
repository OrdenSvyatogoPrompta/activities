<?php

declare(strict_types=1);

namespace App\Dto\Enrollment;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * `branchId` можно не передавать, если у кружка один филиал; без `childId` — заявка «для себя».
 */
final readonly class CreateEnrollmentRequest
{
    public function __construct(
        // Значение по умолчанию — чтобы отсутствующее поле дало русское сообщение NotBlank
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
