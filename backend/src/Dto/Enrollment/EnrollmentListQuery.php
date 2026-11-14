<?php

declare(strict_types=1);

namespace App\Dto\Enrollment;

use App\Entity\Enum\EnrollmentStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * `?status=pending,confirmed` — один или несколько статусов через запятую.
 */
final readonly class EnrollmentListQuery
{
    public function __construct(
        public ?string $status = null,
    ) {
    }

    /**
     * @return list<EnrollmentStatus>|null null — все, кроме cancelled
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
                    ->setParameter('{{ choices }}', implode(', ', EnrollmentStatus::values()))
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
