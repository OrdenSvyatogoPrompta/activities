<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Значения по умолчанию — чтобы отсутствующее поле дало нарушение «Укажите …»,
 * а не ошибку денормализации.
 */
final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите имя', normalizer: 'trim')]
        #[Assert\Length(max: 100, maxMessage: 'Имя не длиннее {{ limit }} символов', normalizer: 'trim')]
        public string $name = '',

        #[Assert\NotBlank(message: 'Укажите email', normalizer: 'trim')]
        #[Assert\Email(message: 'Некорректный email', normalizer: 'trim')]
        #[Assert\Length(max: 180, maxMessage: 'Email не длиннее {{ limit }} символов', normalizer: 'trim')]
        public string $email = '',

        // Верхняя граница — защита от хеширования мегабайтных строк
        #[Assert\Length(
            min: 8,
            max: 4096,
            minMessage: 'Пароль не короче {{ limit }} символов',
            maxMessage: 'Пароль не длиннее {{ limit }} символов',
        )]
        public string $password = '',
    ) {
    }
}
