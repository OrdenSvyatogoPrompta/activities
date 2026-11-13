<?php

declare(strict_types=1);

namespace App\Dto\Children;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Тело `PATCH /api/me/children/{id}` — частичное обновление.
 *
 * Отсутствующее (или null) поле не меняется. Исключение — `lastName`: если поле передано
 * со значением null или пустой строкой, фамилия стирается (наличие ключа проверяет контроллер).
 */
final readonly class UpdateChildRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Имя ребёнка не может быть пустым', allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 50, maxMessage: 'Имя не длиннее {{ limit }} символов', normalizer: 'trim')]
        public ?string $firstName = null,

        #[Assert\Length(max: 50, maxMessage: 'Фамилия не длиннее {{ limit }} символов', normalizer: 'trim')]
        public ?string $lastName = null,

        #[Assert\Range(notInRangeMessage: 'Возраст должен быть от {{ min }} до {{ max }} лет', min: 1, max: 18)]
        public ?int $age = null,

        #[Assert\Choice(choices: ChildGender::VALUES, message: ChildGender::MESSAGE)]
        public ?string $gender = null,
    ) {
    }
}
