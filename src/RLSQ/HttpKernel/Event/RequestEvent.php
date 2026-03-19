<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Event;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

/**
 * Dispatché au début du cycle handle().
 * Un listener peut court-circuiter en définissant une Response.
 */
class RequestEvent extends KernelEvent
{
    private ?Response $response = null;

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
