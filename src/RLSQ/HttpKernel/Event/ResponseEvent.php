<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Event;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

/**
 * Dispatché juste avant de retourner la Response.
 * Permet de modifier headers, contenu, etc.
 */
class ResponseEvent extends KernelEvent
{
    private Response $response;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request);
        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
