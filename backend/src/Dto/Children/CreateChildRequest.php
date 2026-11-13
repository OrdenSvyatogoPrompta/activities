<?php

declare(strict_types=1);

namespace App\Dto\Children;

use App\Entity\Enum\Gender;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Поля nullable, а пол — строка, чтобы ошибки были русскими сообщениями валидатора,
 * а не ошибками денормализации.
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
        #[Assert\Choice(callback: [Gender::class, 'values'], message: 'Пол должен быть male или female')]
        public ?string $gender = null,
    ) {
    }
}
