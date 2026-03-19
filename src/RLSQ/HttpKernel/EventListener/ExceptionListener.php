<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\EventListener;

use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Event\ExceptionEvent;
use RLSQ\HttpKernel\Exception\HttpException;
use RLSQ\HttpKernel\KernelEvents;
use RLSQ\Routing\Exception\MethodNotAllowedException;
use RLSQ\Routing\Exception\RouteNotFoundException;

/**
 * Écoute kernel.exception pour convertir les exceptions en Response.
 */
class ExceptionListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly bool $debug = false,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        $statusCode = match (true) {
            $throwable instanceof HttpException => $throwable->getStatusCode(),
            $throwable instanceof RouteNotFoundException => 404,
            $throwable instanceof MethodNotAllowedException => 405,
            default => 500,
        };

        if ($this->debug) {
            $body = sprintf(
                "<h1>Erreur %d</h1><p>%s</p><pre>%s</pre>",
                $statusCode,
                htmlspecialchars($throwable->getMessage(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($throwable->getTraceAsString(), ENT_QUOTES, 'UTF-8'),
            );
        } else {
            $body = sprintf('<h1>Erreur %d</h1>', $statusCode);
        }

        $response = new Response($body, $statusCode, ['Content-Type' => 'text/html; charset=UTF-8']);

        if ($throwable instanceof MethodNotAllowedException) {
            $response->headers->set('Allow', implode(', ', $throwable->getAllowedMethods()));
        }

        if ($throwable instanceof HttpException) {
            foreach ($throwable->getHeaders() as $key => $value) {
                $response->headers->set($key, $value);
            }
        }

        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -128],
        ];
    }
}
