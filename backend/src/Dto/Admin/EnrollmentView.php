<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Dto\Enrollment\EnrollmentResponse;

/**
 * Формат заявки плюс кружок и заявитель.
 */
final readonly class EnrollmentView implements \JsonSerializable
{
    public function __construct(
        public EnrollmentResponse $enrollment,
        public ClubRefView $club,
        public UserRefView $user,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...get_object_vars($this->enrollment), 'club' => $this->club, 'user' => $this->user];
    }
}
