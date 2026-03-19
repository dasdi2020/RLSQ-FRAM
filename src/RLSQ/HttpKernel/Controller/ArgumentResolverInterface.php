<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller;

use RLSQ\HttpFoundation\Request;

interface ArgumentResolverInterface
{
    /**
     * Résout les arguments à passer au contrôleur.
     *
     * @return array Les arguments dans l'ordre attendu par le contrôleur.
     */
    public function getArguments(Request $request, callable $controller): array;
}
