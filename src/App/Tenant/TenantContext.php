<?php

declare(strict_types=1);

namespace App\Tenant;

/**
 * Service singleton contenant le tenant courant pour la requête.
 */
class TenantContext
{
    private ?array $tenant = null;

    public function setTenant(array $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getTenant(): ?array
    {
        return $this->tenant;
    }

    public function getTenantId(): ?int
    {
        return $this->tenant ? (int) $this->tenant['id'] : null;
    }

    public function getSlug(): ?string
    {
        return $this->tenant['slug'] ?? null;
    }

    public function getName(): ?string
    {
        return $this->tenant['name'] ?? null;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function getDatabaseConfig(): ?array
    {
        if ($this->tenant === null) {
            return null;
        }

        return [
            'driver' => $this->tenant['db_driver'] ?? 'sqlite',
            'host' => $this->tenant['db_host'] ?? 'localhost',
            'port' => $this->tenant['db_port'] ?? '3306',
            'dbname' => $this->tenant['db_name'] ?? '',
            'user' => $this->tenant['db_user'] ?? '',
            'password' => $this->tenant['db_password'] ?? '',
            'path' => $this->tenant['db_path'] ?? '',
        ];
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = json_decode($this->tenant['settings'] ?? '{}', true) ?: [];

        return $settings[$key] ?? $default;
    }

    public function isActive(): bool
    {
        return ($this->tenant['is_active'] ?? 0) == 1;
    }
}
