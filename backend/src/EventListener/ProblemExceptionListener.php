<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\Problem\ProblemFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Приоритет -64: после firewall (1) и логирования ErrorListener (0),
 * но до ErrorListener::onKernelException (-128), который отрисовал бы HTML.
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
