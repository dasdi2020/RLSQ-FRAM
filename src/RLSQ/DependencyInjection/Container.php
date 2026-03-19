<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection;

use RLSQ\DependencyInjection\Exception\ParameterNotFoundException;
use RLSQ\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Container compilé (runtime). Stocke les services instanciés et les paramètres.
 */
class Container implements ContainerInterface
{
    /** @var array<string, mixed> Services déjà instanciés */
    protected array $services = [];

    /** @var array<string, mixed> Paramètres de configuration */
    protected array $parameters = [];

    /** @var array<string, string> Alias : alias -> service id */
    protected array $aliases = [];

    public function __construct()
    {
        // Le container se référence lui-même
        $this->services['service_container'] = $this;
    }

    public function get(string $id): mixed
    {
        // Résoudre l'alias
        $id = $this->aliases[$id] ?? $id;

        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        throw new ServiceNotFoundException($id);
    }

    public function has(string $id): bool
    {
        $id = $this->aliases[$id] ?? $id;

        return isset($this->services[$id]);
    }

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function getParameter(string $name): mixed
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new ParameterNotFoundException($name);
        }

        return $this->parameters[$name];
    }

    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    public function setParameter(string $name, mixed $value): void
    {
        $this->parameters[$name] = $value;
    }

    public function setAlias(string $alias, string $id): void
    {
        $this->aliases[$alias] = $id;
    }
}
