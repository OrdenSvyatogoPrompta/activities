<?php

declare(strict_types=1);

namespace App\Dto\Activity;

final readonly class ActivityResponse
{
    /**
     * @param list<string>                     $tags
     * @param list<ActivityBranchResponse>     $branches
     * @param list<IncomingEnrollmentResponse> $enrollments
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public string $emoji,
        public string $description,
        public string $status,
        public ?string $moderationComment,
        public ?string $clubId,
        public int $ageMin,
        public int $ageMax,
        public string $age,
        public string $visitType,
        public string $priceType,
        public int $priceAmount,
        public string $price,
        public array $tags,
        public string $createdAt,
        public array $branches,
        public array $enrollments,
    ) {
    }
}
