<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ClubPublicationPayload
{
    public function __construct(
        #[Assert\NotNull(message: 'Укажите isPublished: true или false')]
        public ?bool $isPublished = null,
    ) {
    }
}
