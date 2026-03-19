<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation\Session;

class Session implements SessionInterface
{
    private bool $started = false;

    private const FLASH_KEY = '_flashes';

    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return true;
        }

        $this->started = session_start();

        return $this->started;
    }

    public function getId(): string
    {
        return session_id();
    }

    public function setId(string $id): void
    {
        session_id($id);
    }

    public function getName(): string
    {
        return session_name();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();

        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): mixed
    {
        $this->ensureStarted();

        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        return $value;
    }

    public function all(): array
    {
        $this->ensureStarted();

        return $_SESSION;
    }

    public function clear(): void
    {
        $this->ensureStarted();

        $_SESSION = [];
    }

    public function invalidate(): bool
    {
        $this->clear();

        if ($this->started) {
            session_destroy();
            $this->started = false;
        }

        return $this->start();
    }

    public function getFlash(string $type): array
    {
        $this->ensureStarted();

        $flashes = $_SESSION[self::FLASH_KEY][$type] ?? [];

        unset($_SESSION[self::FLASH_KEY][$type]);

        return $flashes;
    }

    public function setFlash(string $type, string $message): void
    {
        $this->ensureStarted();

        $_SESSION[self::FLASH_KEY][$type][] = $message;
    }

    public function hasFlash(string $type): bool
    {
        $this->ensureStarted();

        return !empty($_SESSION[self::FLASH_KEY][$type]);
    }

    public function clearFlashes(): void
    {
        $this->ensureStarted();

        unset($_SESSION[self::FLASH_KEY]);
    }

    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }
}
