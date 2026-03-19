<?php

declare(strict_types=1);

namespace RLSQ\Routing\Matcher;

use RLSQ\Routing\Exception\MethodNotAllowedException;
use RLSQ\Routing\Exception\RouteNotFoundException;
use RLSQ\Routing\RouteCollection;

class UrlMatcher implements UrlMatcherInterface
{
    public function __construct(
        private readonly RouteCollection $routes,
    ) {}

    public function match(string $pathInfo, string $method = 'GET'): array
    {
        $allowedMethods = [];

        foreach ($this->routes->all() as $name => $route) {
            $regex = $route->compile();

            if (!preg_match($regex, $pathInfo, $matches)) {
                continue;
            }

            // Le path matche, mais est-ce que la méthode HTTP est autorisée ?
            if (!$route->allowsMethod($method)) {
                $allowedMethods = array_merge($allowedMethods, $route->getMethods());
                continue;
            }

            // Extraire les paramètres nommés du match regex
            $params = [];
            foreach ($route->getParameterNames() as $paramName) {
                if (isset($matches[$paramName]) && $matches[$paramName] !== '') {
                    $params[$paramName] = $matches[$paramName];
                }
            }

            return array_merge(
                $route->getDefaults(),
                $params,
                [
                    '_route' => $name,
                ],
            );
        }

        // Le path matchait au moins une route mais pas la bonne méthode
        if (!empty($allowedMethods)) {
            throw new MethodNotAllowedException(array_unique($allowedMethods));
        }

        throw new RouteNotFoundException(sprintf('Aucune route ne correspond au chemin "%s".', $pathInfo));
    }
}
