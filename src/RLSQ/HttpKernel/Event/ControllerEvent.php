<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Event;

use RLSQ\HttpFoundation\Request;

/**
 * Dispatché après la résolution du contrôleur.
 * Les listeners peuvent modifier le callable.
 */
class ControllerEvent extends KernelEvent
{
    private mixed $controller;

    public function __construct(Request $request, callable $controller)
    {
        parent::__construct($request);
        $this->controller = $controller;
    }

    public function getController(): callable
    {
        return $this->controller;
    }

    public function setController(callable $controller): void
    {
        $this->controller = $controller;
    }
}
