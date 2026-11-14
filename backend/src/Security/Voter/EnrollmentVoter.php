<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Enrollment;
use App\Entity\User;
use App\Workflow\EnrollmentWorkflow;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Права на заявку; им же следует guard workflow `enrollment` (EnrollmentGuardListener).
 * Кто не видит заявку (VIEW), получает 404, а не 403: чужие id не перебрать.
 *
 * @extends Voter<self::VIEW|self::MODERATE|self::CANCEL, Enrollment>
 */
final class EnrollmentVoter extends Voter
{
    public const string VIEW = 'ENROLLMENT_VIEW';
    public const string MODERATE = 'ENROLLMENT_MODERATE';
    public const string CANCEL = 'ENROLLMENT_CANCEL';

    public const array TRANSITION_ATTRIBUTES = [
        EnrollmentWorkflow::TRANSITION_CONFIRM => self::MODERATE,
        EnrollmentWorkflow::TRANSITION_REJECT => self::MODERATE,
        EnrollmentWorkflow::TRANSITION_CANCEL => self::CANCEL,
    ];

    public const array DENIED_MESSAGES = [
        self::MODERATE => 'Подтверждать и отклонять заявку может только владелец кружка или администратор',
        self::CANCEL => 'Отменить заявку может только её автор',
    ];

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, [self::VIEW, self::MODERATE, self::CANCEL], true);
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
            self::VIEW => $subject->isAuthoredBy($user) || $subject->canBeModeratedBy($user),
            self::MODERATE => $subject->canBeModeratedBy($user),
            self::CANCEL => $subject->canBeCancelledBy($user),
        };
    }
}
