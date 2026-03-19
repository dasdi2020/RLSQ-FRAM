<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\EventListener;

use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Event\ExceptionEvent;
use RLSQ\HttpKernel\Event\RequestEvent;
use RLSQ\HttpKernel\KernelEvents;
use RLSQ\Routing\Exception\MethodNotAllowedException;
use RLSQ\Routing\Exception\RouteNotFoundException;
use RLSQ\Routing\Matcher\UrlMatcherInterface;

/**
 * Écoute kernel.request pour effectuer le routing.
 * Enrichit $request->attributes avec _controller, _route et les paramètres.
 */
class RouterListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlMatcherInterface $matcher,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $parameters = $this->matcher->match(
            $request->getPathInfo(),
            $request->getMethod(),
        );

        foreach ($parameters as $key => $value) {
            $request->attributes->set($key, $value);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 32],
        ];
    }
}
