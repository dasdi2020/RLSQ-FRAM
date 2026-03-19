<?php

declare(strict_types=1);

namespace RLSQ\Routing;

use RLSQ\Routing\Generator\UrlGenerator;
use RLSQ\Routing\Generator\UrlGeneratorInterface;
use RLSQ\Routing\Matcher\UrlMatcher;
use RLSQ\Routing\Matcher\UrlMatcherInterface;

/**
 * Façade regroupant le matcher et le générateur d'URLs.
 */
class Router implements UrlMatcherInterface, UrlGeneratorInterface
{
    private RouteCollection $routes;
    private UrlMatcher $matcher;
    private UrlGenerator $generator;

    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
        $this->matcher = new UrlMatcher($routes);
        $this->generator = new UrlGenerator($routes);
    }

    public function match(string $pathInfo, string $method = 'GET'): array
    {
        return $this->matcher->match($pathInfo, $method);
    }

    public function generate(string $name, array $parameters = []): string
    {
        return $this->generator->generate($name, $parameters);
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->routes;
    }
}
