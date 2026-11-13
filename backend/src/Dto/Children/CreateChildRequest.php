<?php

declare(strict_types=1);

namespace App\Dto\Children;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Тело `POST /api/me/children`: `{ firstName, lastName?, age, gender }`.
 *
 * Поля nullable, чтобы отсутствующее поле давало понятное сообщение валидатора,
 * а не ошибку денормализации.
 */
final readonly class CreateChildRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите имя ребёнка', normalizer: 'trim')]
        #[Assert\Length(max: 50, maxMessage: 'Имя не длиннее {{ limit }} символов', normalizer: 'trim')]
        public ?string $firstName = null,

        #[Assert\Length(max: 50, maxMessage: 'Фамилия не длиннее {{ limit }} символов', normalizer: 'trim')]
        public ?string $lastName = null,

        #[Assert\NotNull(message: 'Укажите возраст ребёнка')]
        #[Assert\Range(notInRangeMessage: 'Возраст должен быть от {{ min }} до {{ max }} лет', min: 1, max: 18)]
        public ?int $age = null,

        #[Assert\NotNull(message: 'Укажите пол ребёнка')]
        #[Assert\Choice(choices: ChildGender::VALUES, message: ChildGender::MESSAGE)]
        public ?string $gender = null,
    ) {
    }
}
