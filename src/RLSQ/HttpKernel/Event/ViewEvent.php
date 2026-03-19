<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Event;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

/**
 * Dispatché quand le contrôleur ne retourne pas une Response.
 * Un listener doit convertir la valeur de retour en Response.
 */
class ViewEvent extends KernelEvent
{
    private mixed $controllerResult;
    private ?Response $response = null;

    public function __construct(Request $request, mixed $controllerResult)
    {
        parent::__construct($request);
        $this->controllerResult = $controllerResult;
    }

    public function getControllerResult(): mixed
    {
        return $this->controllerResult;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
        $this->stopPropagation();
    }

    public function hasResponse(): bool
    {
        return $this->response !== null;
    }
}
