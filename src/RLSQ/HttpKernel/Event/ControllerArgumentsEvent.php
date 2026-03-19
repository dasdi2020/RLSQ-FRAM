<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Event;

use RLSQ\HttpFoundation\Request;

/**
 * Dispatché après la résolution des arguments du contrôleur.
 * Les listeners peuvent modifier les arguments.
 */
class ControllerArgumentsEvent extends KernelEvent
{
    private array $arguments;
    private mixed $controller;

    public function __construct(Request $request, callable $controller, array $arguments)
    {
        parent::__construct($request);
        $this->controller = $controller;
        $this->arguments = $arguments;
    }

    public function getController(): callable
    {
        return $this->controller;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }
}
