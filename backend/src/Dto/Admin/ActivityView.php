<?php

declare(strict_types=1);

namespace App\Dto\Admin;

/**
 * Активность для администратора: формат `Activity` из контракта группы G + `owner`.
 */
final readonly class ActivityView
{
    /**
     * @param list<string>                 $tags
     * @param list<ActivityBranchView>     $branches
     * @param list<ActivityEnrollmentView> $enrollments
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
        public string $visitType,
        public string $priceType,
        public int $priceAmount,
        public array $tags,
        public array $branches,
        public array $enrollments,
        public UserRefView $owner,
    ) {
    }
}
