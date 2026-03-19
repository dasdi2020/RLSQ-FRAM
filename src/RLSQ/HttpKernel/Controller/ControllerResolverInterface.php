<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller;

use RLSQ\HttpFoundation\Request;

interface ControllerResolverInterface
{
    /**
     * Résout le contrôleur à partir de la Request.
     *
     * @return callable|false Le callable contrôleur ou false si non résolu.
     */
    public function getController(Request $request): callable|false;
}
