<?php

declare(strict_types=1);

namespace RLSQ\Profiler\Collector;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\Profiler\DataCollectorInterface;

class RequestCollector implements DataCollectorInterface
{
    private array $data = [];

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'uri' => $request->getUri(),
            'query' => $request->query->all(),
            'request' => $request->request->all(),
            'headers' => $request->headers->all(),
            'server' => [
                'PHP_VERSION' => PHP_VERSION,
                'SERVER_SOFTWARE' => $request->server->get('SERVER_SOFTWARE', ''),
                'HTTP_HOST' => $request->getHost(),
            ],
            'attributes' => $request->attributes->all(),
            'content_type' => $response->headers->get('content-type'),
            'status_code' => $response->getStatusCode(),
            'response_headers' => $response->headers->all(),
            'client_ip' => $request->getClientIp(),
        ];
    }

    public function getName(): string
    {
        return 'request';
    }

    public function getData(): array
    {
        return $this->data;
    }
}
