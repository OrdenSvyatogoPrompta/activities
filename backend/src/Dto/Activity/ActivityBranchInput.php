<?php

declare(strict_types=1);

namespace App\Dto\Activity;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ActivityBranchInput
{
    /**
     * @param list<ScheduleSlotInput>|null $slots
     */
    public function __construct(
        #[Assert\Length(max: 100, maxMessage: 'Название филиала — не длиннее {{ limit }} символов')]
        public ?string $name = null,

        #[Assert\NotBlank(message: 'Укажите адрес филиала', normalizer: 'trim')]
        #[Assert\Length(max: 255, maxMessage: 'Адрес — не длиннее {{ limit }} символов')]
        public ?string $address = null,

        #[Assert\Length(max: 32, maxMessage: 'Телефон — не длиннее {{ limit }} символов')]
        public ?string $phone = null,

        /** @var list<ScheduleSlotInput>|null */
        #[Assert\NotNull(message: 'Укажите расписание филиала')]
        #[Assert\Count(min: 1, max: 50, minMessage: 'Добавьте хотя бы одно занятие в расписание', maxMessage: 'Не больше {{ limit }} занятий в расписании филиала')]
        #[Assert\Valid]
        public ?array $slots = null,
    ) {
    }
}
