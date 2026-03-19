<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection;

/**
 * Décrit comment construire un service : classe, arguments, appels de méthodes, tags.
 */
class Definition
{
    private ?string $class = null;
    private array $arguments = [];
    /** @var array<array{method: string, arguments: array}> */
    private array $methodCalls = [];
    /** @var array<string, array<array<string, mixed>>> */
    private array $tags = [];
    private bool $shared = true;
    private ?string $factory = null;
    private ?array $factoryMethod = null;
    private bool $autowire = false;
    private bool $public = true;

    public function __construct(?string $class = null, array $arguments = [])
    {
        $this->class = $class;
        $this->arguments = $arguments;
    }

    // --- Class ---

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): static
    {
        $this->class = $class;
        return $this;
    }

    // --- Arguments ---

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function addArgument(mixed $argument): static
    {
        $this->arguments[] = $argument;
        return $this;
    }

    public function setArgument(int $index, mixed $argument): static
    {
        $this->arguments[$index] = $argument;
        return $this;
    }

    // --- Method calls ---

    public function addMethodCall(string $method, array $arguments = []): static
    {
        $this->methodCalls[] = ['method' => $method, 'arguments' => $arguments];
        return $this;
    }

    /**
     * @return array<array{method: string, arguments: array}>
     */
    public function getMethodCalls(): array
    {
        return $this->methodCalls;
    }

    // --- Tags ---

    public function addTag(string $name, array $attributes = []): static
    {
        $this->tags[$name][] = $attributes;
        return $this;
    }

    public function hasTag(string $name): bool
    {
        return isset($this->tags[$name]);
    }

    public function getTag(string $name): array
    {
        return $this->tags[$name] ?? [];
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function clearTag(string $name): static
    {
        unset($this->tags[$name]);
        return $this;
    }

    // --- Shared ---

    public function isShared(): bool
    {
        return $this->shared;
    }

    public function setShared(bool $shared): static
    {
        $this->shared = $shared;
        return $this;
    }

    // --- Factory ---

    public function setFactory(string $class, string $method): static
    {
        $this->factoryMethod = [$class, $method];
        return $this;
    }

    public function getFactory(): ?array
    {
        return $this->factoryMethod;
    }

    // --- Autowire ---

    public function isAutowired(): bool
    {
        return $this->autowire;
    }

    public function setAutowired(bool $autowire): static
    {
        $this->autowire = $autowire;
        return $this;
    }

    // --- Public ---

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): static
    {
        $this->public = $public;
        return $this;
    }
}
