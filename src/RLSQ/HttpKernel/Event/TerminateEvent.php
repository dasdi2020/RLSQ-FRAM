<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Event;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

/**
 * Dispatché après Response::send(), pour le post-traitement
 * (logging, fermeture de connexions, etc.).
 */
class TerminateEvent extends KernelEvent
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
}
