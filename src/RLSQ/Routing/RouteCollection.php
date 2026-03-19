<?php

declare(strict_types=1);

namespace RLSQ\Routing;

class RouteCollection
{
    /** @var array<string, Route> */
    private array $routes = [];

    public function add(string $name, Route $route): void
    {
        $this->routes[$name] = $route;
    }

    public function get(string $name): ?Route
    {
        return $this->routes[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    public function remove(string $name): void
    {
        unset($this->routes[$name]);
    }

    /**
     * @return array<string, Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Fusionne une autre collection dans celle-ci.
     */
    public function addCollection(RouteCollection $collection): void
    {
        foreach ($collection->all() as $name => $route) {
            $this->add($name, $route);
        }
    }

    /**
     * Ajoute un préfixe à toutes les routes de la collection.
     */
    public function addPrefix(string $prefix): void
    {
        $prefix = '/' . ltrim($prefix, '/');

        foreach ($this->routes as $route) {
            $route->setPath($prefix . $route->getPath());
        }
    }
}
