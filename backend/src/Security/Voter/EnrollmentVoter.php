<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Enrollment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Права на заявку (правило D6):
 * - MODERATE (подтвердить / отклонить) — владелец кружка или ROLE_ADMIN;
 *   заявки в кружки без владельца — только ROLE_ADMIN;
 * - CANCEL (отменить) — только автор заявки.
 *
 * Логика — в Enrollment::canBeModeratedBy() / canBeCancelledBy(), те же методы использует
 * guard workflow `enrollment` (App\Workflow\EnrollmentGuardListener).
 *
 * Использование: `$this->denyAccessUnlessGranted(EnrollmentVoter::MODERATE, $enrollment)`
 * или `#[IsGranted(EnrollmentVoter::CANCEL, subject: 'enrollment')]`.
 *
 * @extends Voter<self::MODERATE|self::CANCEL, Enrollment>
 */
final class EnrollmentVoter extends Voter
{
    public const string MODERATE = 'ENROLLMENT_MODERATE';
    public const string CANCEL = 'ENROLLMENT_CANCEL';

    public function supportsAttribute(string $attribute): bool
    {
        return self::MODERATE === $attribute || self::CANCEL === $attribute;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Enrollment::class, true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute) && $subject instanceof Enrollment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('Требуется вход');

            return false;
        }

        \assert($subject instanceof Enrollment);

        $granted = match ($attribute) {
            self::MODERATE => $subject->canBeModeratedBy($user),
            self::CANCEL => $subject->canBeCancelledBy($user),
            default => false,
        };

        if (!$granted) {
            $vote?->addReason(self::MODERATE === $attribute
                ? 'Подтверждать и отклонять заявку может только владелец кружка или администратор'
                : 'Отменить заявку может только её автор');
        }

        return $granted;
    }
}
