<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Статус заявки на запись. Значения — места (places) workflow `enrollment`
 * (config/packages/workflow.yaml). Менять статус — только через workflow.
 */
enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Активная заявка занимает место участника в кружке: вторую такую же создать нельзя,
     * ребёнка с активными заявками удалить нельзя.
     */
    public function isActive(): bool
    {
        return self::Pending === $this || self::Confirmed === $this;
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }
}
