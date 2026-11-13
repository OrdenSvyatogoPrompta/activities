<?php

declare(strict_types=1);

namespace App\Dto\Admin;

/**
 * Филиал активности в формате `Activity.branches[]` (контракт группы G).
 * `days/timeFrom/timeTo` — для совместимости с фронтом: дни всех слотов и время первого слота.
 */
final readonly class ActivityBranchView
{
    /**
     * @param list<string>   $days
     * @param list<SlotView> $slots
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public ?string $phone,
        public array $days,
        public ?string $timeFrom,
        public ?string $timeTo,
        public array $slots,
    ) {
    }
}
