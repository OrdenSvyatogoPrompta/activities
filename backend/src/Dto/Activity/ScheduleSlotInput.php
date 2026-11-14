<?php

declare(strict_types=1);

namespace App\Dto\Activity;

use App\Entity\Enum\DayOfWeek;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class ScheduleSlotInput
{
    private const string TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(
        #[Assert\NotBlank(message: 'Укажите день недели')]
        #[Assert\Choice(callback: [DayOfWeek::class, 'values'], message: 'Допустимые дни: Пн, Вт, Ср, Чт, Пт, Сб, Вс')]
        public ?string $day = null,

        #[Assert\NotBlank(message: 'Укажите время начала')]
        #[Assert\Regex(pattern: self::TIME_PATTERN, message: 'Время в формате ЧЧ:ММ, например 15:30')]
        public ?string $timeFrom = null,

        #[Assert\NotBlank(message: 'Укажите время окончания')]
        #[Assert\Regex(pattern: self::TIME_PATTERN, message: 'Время в формате ЧЧ:ММ, например 17:30')]
        public ?string $timeTo = null,
    ) {
    }

    #[Assert\Callback]
    public function validateTimeRange(ExecutionContextInterface $context): void
    {
        if (null === $this->timeFrom || null === $this->timeTo
            || 1 !== preg_match(self::TIME_PATTERN, $this->timeFrom)
            || 1 !== preg_match(self::TIME_PATTERN, $this->timeTo)) {
            return;
        }

        // ЧЧ:ММ с ведущими нулями сравниваются как строки
        if ($this->timeFrom >= $this->timeTo) {
            $context->buildViolation('Время окончания должно быть позже времени начала')
                ->atPath('timeTo')
                ->addViolation();
        }
    }
}
