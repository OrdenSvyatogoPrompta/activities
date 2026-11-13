<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\Problem\ProblemFactory;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TooManyRefreshRequestsException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Ошибки аутентификации (lexik и gesdinet отвечают своим JSON) → `application/problem+json`.
 */
final readonly class AuthenticationFailureProblemListener
{
    public function __construct(
        private ProblemFactory $problems,
    ) {
    }

    /**
     * Запрос к защищённому маршруту без заголовка Authorization (точка входа firewall).
     */
    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onJwtNotFound(AuthenticationFailureEvent $event): void
    {
        $event->setResponse($this->problems->authenticationProblem('Требуется вход: access-токен не передан'));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onJwtExpired(AuthenticationFailureEvent $event): void
    {
        $event->setResponse($this->problems->authenticationProblem('Срок действия access-токена истёк'));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onJwtInvalid(AuthenticationFailureEvent $event): void
    {
        $event->setResponse($this->problems->authenticationProblem('Access-токен недействителен'));
    }

    /**
     * Неуспешный вход через json_login (обработчик неудачи lexik).
     */
    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onLoginFailure(AuthenticationFailureEvent $event): void
    {
        if ($event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            $event->setResponse($this->problems->authenticationProblem(
                'Слишком много попыток входа, попробуйте через минуту',
                Response::HTTP_TOO_MANY_REQUESTS,
            ));

            return;
        }

        $event->setResponse($this->problems->authenticationProblem('Неверный email или пароль'));
    }

    /**
     * Неуспешный POST /api/auth/refresh.
     */
    #[AsEventListener(event: 'gesdinet.refresh_token_failure')]
    public function onRefreshFailure(RefreshAuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();

        if ($exception instanceof TooManyRefreshRequestsException) {
            $response = $this->problems->authenticationProblem('Слишком много запросов обновления токена', Response::HTTP_TOO_MANY_REQUESTS);
            $retryAfter = $event->getResponse()->headers->get('Retry-After');
            if (null !== $retryAfter) {
                $response->headers->set('Retry-After', $retryAfter);
            }
            $event->setResponse($response);

            return;
        }

        $event->setResponse($this->problems->authenticationProblem(
            $exception instanceof MissingTokenException
                ? 'Refresh-токен не передан'
                : 'Refresh-токен недействителен или истёк',
        ));
    }
}
