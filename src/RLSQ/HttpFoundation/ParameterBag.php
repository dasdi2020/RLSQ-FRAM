<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class ParameterBag
{
    public function __construct(
        protected array $parameters = [],
    ) {}

    public function all(): array
    {
        return $this->parameters;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }

    public function remove(string $key): void
    {
        unset($this->parameters[$key]);
    }

    public function keys(): array
    {
        return array_keys($this->parameters);
    }

    public function count(): int
    {
        return count($this->parameters);
    }
}
