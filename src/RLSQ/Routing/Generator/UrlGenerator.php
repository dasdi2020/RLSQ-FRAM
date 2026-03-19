<?php

declare(strict_types=1);

namespace RLSQ\Routing\Generator;

use RLSQ\Routing\Exception\RouteNotFoundException;
use RLSQ\Routing\RouteCollection;

class UrlGenerator implements UrlGeneratorInterface
{
    public function __construct(
        private readonly RouteCollection $routes,
    ) {}

    public function generate(string $name, array $parameters = []): string
    {
        $route = $this->routes->get($name);

        if ($route === null) {
            throw new RouteNotFoundException(sprintf('La route "%s" n\'existe pas.', $name));
        }

        $path = $route->getPath();

        // Remplacer les placeholders {param} par les valeurs fournies ou les defaults
        $usedParams = [];

        $url = preg_replace_callback(
            '#\{(\w+)\}#',
            function (array $matches) use ($parameters, $route, &$usedParams): string {
                $param = $matches[1];
                $usedParams[] = $param;

                if (isset($parameters[$param])) {
                    return (string) $parameters[$param];
                }

                $default = $route->getDefault($param);
                if ($default !== null) {
                    return (string) $default;
                }

                throw new \InvalidArgumentException(sprintf(
                    'Le paramètre "%s" est requis pour la route "%s".',
                    $param,
                    $route->getPath(),
                ));
            },
            $path,
        );

        // Les paramètres non utilisés dans le path deviennent des query string
        $extra = array_diff_key($parameters, array_flip($usedParams));

        // Exclure les paramètres internes (_controller, _route, etc.)
        $extra = array_filter($extra, fn (string $key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);

        if (!empty($extra)) {
            $url .= '?' . http_build_query($extra);
        }

        return $url;
    }
}
