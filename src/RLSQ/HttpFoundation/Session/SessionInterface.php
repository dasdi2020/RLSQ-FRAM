<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation\Session;

interface SessionInterface
{
    public function start(): bool;

    public function getId(): string;

    public function setId(string $id): void;

    public function getName(): string;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): mixed;

    public function all(): array;

    public function clear(): void;

    public function invalidate(): bool;

    /**
     * Messages flash : affichés une seule fois puis supprimés.
     */
    public function getFlash(string $type): array;

    public function setFlash(string $type, string $message): void;

    public function hasFlash(string $type): bool;

    public function clearFlashes(): void;
}
