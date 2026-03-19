<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Event;

use RLSQ\EventDispatcher\Event;
use RLSQ\HttpFoundation\Request;

/**
 * Classe de base pour tous les événements du Kernel.
 */
class KernelEvent extends Event
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function getRequest(): Request
    {
        return $this->request;
    }
}
