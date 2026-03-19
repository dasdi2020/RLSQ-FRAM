<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel;

use RLSQ\EventDispatcher\EventDispatcherInterface;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolverInterface;
use RLSQ\HttpKernel\Controller\ControllerResolverInterface;
use RLSQ\HttpKernel\Event\ControllerArgumentsEvent;
use RLSQ\HttpKernel\Event\ControllerEvent;
use RLSQ\HttpKernel\Event\ExceptionEvent;
use RLSQ\HttpKernel\Event\RequestEvent;
use RLSQ\HttpKernel\Event\ResponseEvent;
use RLSQ\HttpKernel\Event\TerminateEvent;
use RLSQ\HttpKernel\Event\ViewEvent;

class HttpKernel implements HttpKernelInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ControllerResolverInterface $controllerResolver,
        private readonly ArgumentResolverInterface $argumentResolver,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            return $this->handleRaw($request);
        } catch (\Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * Cycle complet Request → Response.
     */
    private function handleRaw(Request $request): Response
    {
        // 1. kernel.request — le RouterListener fait le matching ici
        $event = new RequestEvent($request);
        $this->dispatcher->dispatch($event, KernelEvents::REQUEST);

        if ($event->hasResponse()) {
            return $this->filterResponse($event->getResponse(), $request);
        }

        // 2. Résolution du contrôleur
        $controller = $this->controllerResolver->getController($request);

        if ($controller === false) {
            throw new \LogicException(sprintf(
                'Impossible de trouver un contrôleur pour le chemin "%s". Vérifiez que la route définit bien un _controller.',
                $request->getPathInfo(),
            ));
        }

        // 3. kernel.controller — les listeners peuvent modifier le contrôleur
        $controllerEvent = new ControllerEvent($request, $controller);
        $this->dispatcher->dispatch($controllerEvent, KernelEvents::CONTROLLER);
        $controller = $controllerEvent->getController();

        // 4. Résolution des arguments
        $arguments = $this->argumentResolver->getArguments($request, $controller);

        // 5. kernel.controller_arguments — les listeners peuvent modifier les arguments
        $argsEvent = new ControllerArgumentsEvent($request, $controller, $arguments);
        $this->dispatcher->dispatch($argsEvent, KernelEvents::CONTROLLER_ARGUMENTS);
        $arguments = $argsEvent->getArguments();

        // 6. Appel du contrôleur
        $response = call_user_func_array($controller, $arguments);

        // 7. Si le contrôleur ne retourne pas une Response, dispatch kernel.view
        if (!$response instanceof Response) {
            $viewEvent = new ViewEvent($request, $response);
            $this->dispatcher->dispatch($viewEvent, KernelEvents::VIEW);

            if ($viewEvent->hasResponse()) {
                $response = $viewEvent->getResponse();
            } else {
                throw new \LogicException(sprintf(
                    'Le contrôleur doit retourner une Response. "%s" retourné.',
                    get_debug_type($response),
                ));
            }
        }

        // 8. kernel.response — modification finale
        return $this->filterResponse($response, $request);
    }

    /**
     * Dispatche kernel.response pour permettre la modification de la Response.
     */
    private function filterResponse(Response $response, Request $request): Response
    {
        $event = new ResponseEvent($request, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        return $event->getResponse();
    }

    /**
     * Gère une exception en la convertissant en Response via kernel.exception.
     */
    private function handleException(Request $request, \Throwable $exception): Response
    {
        $event = new ExceptionEvent($request, $exception);
        $this->dispatcher->dispatch($event, KernelEvents::EXCEPTION);

        if (!$event->hasResponse()) {
            throw $exception;
        }

        $response = $event->getResponse();

        // Filtrer la Response d'erreur aussi
        try {
            return $this->filterResponse($response, $request);
        } catch (\Throwable) {
            return $response;
        }
    }

    /**
     * À appeler après Response::send() pour le post-traitement.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->dispatcher->dispatch(
            new TerminateEvent($request, $response),
            KernelEvents::TERMINATE,
        );
    }
}
