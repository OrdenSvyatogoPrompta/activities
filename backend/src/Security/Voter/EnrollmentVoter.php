<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Enrollment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Права на заявку; им же следует guard workflow `enrollment` (EnrollmentGuardListener).
 *
 * @extends Voter<self::MODERATE|self::CANCEL, Enrollment>
 */
final class EnrollmentVoter extends Voter
{
    public const string MODERATE = 'ENROLLMENT_MODERATE';
    public const string CANCEL = 'ENROLLMENT_CANCEL';

    public const array DENIED_MESSAGES = [
        self::MODERATE => 'Подтверждать и отклонять заявку может только владелец кружка или администратор',
        self::CANCEL => 'Отменить заявку может только её автор',
    ];

    public function supportsAttribute(string $attribute): bool
    {
        return isset(self::DENIED_MESSAGES[$attribute]);
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
        \assert($subject instanceof Enrollment);

        return $user instanceof User && match ($attribute) {
            self::MODERATE => $subject->canBeModeratedBy($user),
            self::CANCEL => $subject->canBeCancelledBy($user),
        };
    }
}
