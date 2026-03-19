<?php

declare(strict_types=1);

namespace RLSQ\Config\Loader;

interface LoaderInterface
{
    /**
     * Charge une ressource de configuration.
     */
    public function load(string $resource): array;

    /**
     * Vérifie si ce loader supporte la ressource.
     */
    public function supports(string $resource): bool;
}
