<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class HeaderBag
{
    protected array $headers = [];

    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function all(): array
    {
        return $this->headers;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $key = strtolower($key);

        if (!isset($this->headers[$key])) {
            return $default;
        }

        return $this->headers[$key][0] ?? $default;
    }

    public function set(string $key, string|array $values): void
    {
        $key = strtolower($key);
        $this->headers[$key] = is_array($values) ? array_values($values) : [$values];
    }

    public function has(string $key): bool
    {
        return isset($this->headers[strtolower($key)]);
    }

    public function remove(string $key): void
    {
        unset($this->headers[strtolower($key)]);
    }
}
