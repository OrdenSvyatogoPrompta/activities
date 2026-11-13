<?php

declare(strict_types=1);

namespace App\Dto\Children;

use App\Entity\Enum\Gender;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * null — поле не меняется; кроме `lastName`: переданный null стирает фамилию.
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

        #[Assert\Choice(callback: [Gender::class, 'values'], message: 'Пол должен быть male или female')]
        public ?string $gender = null,
    ) {
    }
}
