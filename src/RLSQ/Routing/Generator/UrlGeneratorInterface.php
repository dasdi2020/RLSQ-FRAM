<?php

declare(strict_types=1);

namespace RLSQ\Routing\Generator;

interface UrlGeneratorInterface
{
    /**
     * Génère une URL à partir du nom de route et des paramètres.
     *
     * @throws \RLSQ\Routing\Exception\RouteNotFoundException
     */
    public function generate(string $name, array $parameters = []): string;
}
