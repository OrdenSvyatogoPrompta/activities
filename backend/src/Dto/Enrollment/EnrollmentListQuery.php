<?php

declare(strict_types=1);

namespace App\Dto\Enrollment;

use App\Entity\Enum\EnrollmentStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Query-параметры `GET /api/me/enrollments`.
 *
 * `status` — один статус или несколько через запятую: `?status=pending`,
 * `?status=pending,confirmed,rejected,cancelled` (все). Не задан — все, кроме `cancelled`.
 */
final readonly class EnrollmentListQuery
{
    public function __construct(
        public ?string $status = null,
    ) {
    }

    /**
     * Запрошенные статусы; null — значение по умолчанию (все, кроме cancelled).
     *
     * @return list<EnrollmentStatus>|null
     */
    public function statuses(): ?array
    {
        $values = $this->values();
        if ([] === $values) {
            return null;
        }

        return array_map(EnrollmentStatus::from(...), array_values(array_unique($values)));
    }

    #[Assert\Callback]
    public function validateStatus(ExecutionContextInterface $context): void
    {
        foreach ($this->values() as $value) {
            if (null === EnrollmentStatus::tryFrom($value)) {
                $context->buildViolation('Неизвестный статус «{{ value }}». Допустимые: {{ choices }}')
                    ->setParameter('{{ value }}', $value)
                    ->setParameter('{{ choices }}', implode(', ', array_map(
                        static fn (EnrollmentStatus $status): string => $status->value,
                        EnrollmentStatus::cases(),
                    )))
                    ->atPath('status')
                    ->addViolation();
            }
        }
    }

    /**
     * @return list<string>
     */
    private function values(): array
    {
        if (null === $this->status) {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $this->status)),
            static fn (string $value): bool => '' !== $value,
        ));
    }
}
