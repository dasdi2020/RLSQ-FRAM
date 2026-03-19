<?php

declare(strict_types=1);

namespace RLSQ\Profiler;

use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\HttpKernel\Event\ResponseEvent;
use RLSQ\HttpKernel\KernelEvents;

/**
 * Injecte la Web Debug Toolbar dans les réponses HTML.
 */
class ProfilerListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Profiler $profiler,
        private readonly WebDebugToolbar $toolbar,
        private readonly bool $enabled = true,
    ) {}

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Ne pas injecter dans les réponses JSON, redirections, etc.
        $contentType = $response->headers->get('content-type') ?? '';
        if (!str_contains($contentType, 'text/html') && $contentType !== '') {
            return;
        }

        // Ne pas injecter dans les requêtes AJAX
        if ($request->isXmlHttpRequest()) {
            return;
        }

        // Collecter les données
        $this->profiler->collect($request, $response);

        // Injecter la toolbar avant </body>
        $content = $response->getContent();
        $toolbarHtml = $this->toolbar->render($this->profiler);

        $pos = strripos($content, '</body>');
        if ($pos !== false) {
            $content = substr($content, 0, $pos) . $toolbarHtml . "\n" . substr($content, $pos);
        } else {
            $content .= $toolbarHtml;
        }

        $response->setContent($content);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
        ];
    }
}
