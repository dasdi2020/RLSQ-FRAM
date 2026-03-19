<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Event;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

/**
 * Dispatché quand une exception est levée pendant handle().
 * Un listener peut convertir l'exception en Response.
 */
class ExceptionEvent extends KernelEvent
{
    private \Throwable $throwable;
    private ?Response $response = null;

    public function __construct(Request $request, \Throwable $throwable)
    {
        parent::__construct($request);
        $this->throwable = $throwable;
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
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
