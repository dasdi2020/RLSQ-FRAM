<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection;

/**
 * Interface PSR-11 simplifiée pour le Service Container.
 */
interface ContainerInterface
{
    public function get(string $id): mixed;

    public function has(string $id): bool;

    public function getParameter(string $name): mixed;

    public function hasParameter(string $name): bool;
}
