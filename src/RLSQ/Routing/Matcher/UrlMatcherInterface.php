<?php

declare(strict_types=1);

namespace RLSQ\Routing\Matcher;

interface UrlMatcherInterface
{
    /**
     * Trouve la route correspondant au chemin donné.
     *
     * @return array Tableau contenant _route, _controller et les paramètres extraits.
     *
     * @throws \RLSQ\Routing\Exception\RouteNotFoundException
     * @throws \RLSQ\Routing\Exception\MethodNotAllowedException
     */
    public function match(string $pathInfo, string $method = 'GET'): array;
}
