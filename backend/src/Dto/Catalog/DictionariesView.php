<?php

declare(strict_types=1);

namespace App\Dto\Catalog;

final readonly class DictionariesView
{
    /**
     * @param list<string> $categories
     * @param list<string> $days
     * @param list<string> $priceTypes
     * @param list<string> $visitTypes
     */
    public function __construct(
        public array $categories,
        public array $days,
        public array $priceTypes,
        public array $visitTypes,
    ) {
    }
}
