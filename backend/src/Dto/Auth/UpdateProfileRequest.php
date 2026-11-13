<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProfileRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите имя', allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 100, maxMessage: 'Имя не длиннее {{ limit }} символов', normalizer: 'trim')]
        public ?string $name = null,
    ) {
    }
}
