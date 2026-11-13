<?php

declare(strict_types=1);

namespace App\Dto\Catalog;

/**
 * `address`, `phone`, `schedule` верхнего уровня — первого филиала: так их читает фронт.
 */
final readonly class ClubView
{
    /**
     * @param list<string>     $categories
     * @param list<string>     $days
     * @param list<string>     $tags
     * @param list<BranchView> $branches
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
