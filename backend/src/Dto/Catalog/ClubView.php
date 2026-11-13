<?php

declare(strict_types=1);

namespace App\Dto\Catalog;

/**
 * Кружок каталога в API (`Club` из models/club.model.ts).
 *
 * `id` — slug. `address`, `phone`, `schedule` верхнего уровня — данные первого филиала
 * (для совместимости с моками), `branches` возвращается всегда.
 */
final readonly class ClubView
{
    /**
     * @param list<string>     $categories
     * @param list<string>     $days       объединение дней всех филиалов, Пн…Вс
     * @param list<string>     $tags
     * @param list<BranchView> $branches   в порядке position
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $categories,
        public string $emoji,
        public string $color,
        public string $shortDescription,
        public string $fullDescription,
        public string $age,
        public int $ageMin,
        public int $ageMax,
        public array $days,
        public string $visitType,
        public string $schedule,
        public string $address,
        public string $phone,
        public string $price,
        public int $priceAmount,
        public string $priceType,
        public array $tags,
        public array $branches,
    ) {
    }
}
