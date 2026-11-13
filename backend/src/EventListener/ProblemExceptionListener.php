<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\Problem\ProblemFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Любое необработанное исключение → `application/problem+json` вместо HTML-страницы ошибки.
 *
 * Приоритет -64 выбран между слушателями фреймворка:
 * - после firewall (1): он превращает AccessDenied в 401 через точку входа JWT
 *   или в AccessDeniedHttpException (403);
 * - после ErrorListener::logKernelException (0): исключение уже залогировано,
 *   а `#[WithHttpStatus]` превращён в HttpException;
 * - до ErrorListener::onKernelException (-128), который отрисовал бы HTML.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final readonly class ProblemExceptionListener
{
    public function __construct(
        private ProblemFactory $problems,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $event->setResponse($this->problems->fromThrowable($event->getThrowable()));
    }
}
