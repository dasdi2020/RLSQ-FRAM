<?php

declare(strict_types=1);

namespace RLSQ\Profiler\Collector;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\Profiler\DataCollectorInterface;

class RouteCollector implements DataCollectorInterface
{
    private array $data = [];

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'route' => $request->attributes->get('_route', 'N/A'),
            'controller' => $this->formatController($request->attributes->get('_controller')),
            'route_params' => array_filter(
                $request->attributes->all(),
                fn (string $key) => !str_starts_with($key, '_'),
                ARRAY_FILTER_USE_KEY,
            ),
        ];
    }

    public function getName(): string
    {
        return 'route';
    }

    public function getData(): array
    {
        return $this->data;
    }

    private function formatController(mixed $controller): string
    {
        if ($controller === null) {
            return 'N/A';
        }

        if (is_string($controller)) {
            return $controller;
        }

        if ($controller instanceof \Closure) {
            $ref = new \ReflectionFunction($controller);
            return sprintf('Closure (%s:%d)', basename($ref->getFileName()), $ref->getStartLine());
        }

        if (is_array($controller) && count($controller) === 2) {
            return $controller[0]::class . '::' . $controller[1];
        }

        return get_debug_type($controller);
    }
}
