<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use App\Tenant\TenantContext;
use RLSQ\Database\Connection;

/**
 * Crée et cache les connexions par tenant.
 */
class TenantConnectionFactory
{
    /** @var array<int, Connection> */
    private array $connections = [];

    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Retourne la connexion pour le tenant courant.
     */
    public function getConnection(TenantContext $context): Connection
    {
        $tenantId = $context->getTenantId();

        if ($tenantId === null) {
            throw new \RuntimeException('Aucun tenant dans le contexte.');
        }

        if (isset($this->connections[$tenantId])) {
            return $this->connections[$tenantId];
        }

        $config = $context->getDatabaseConfig();

        if ($config === null) {
            throw new \RuntimeException('Pas de configuration de base de données pour ce tenant.');
        }

        // Résoudre les chemins relatifs pour SQLite
        if ($config['driver'] === 'sqlite' && !empty($config['path'])) {
            $path = $config['path'];
            if (!str_starts_with($path, '/') && !(strlen($path) > 2 && $path[1] === ':') && $path !== ':memory:') {
                $config['path'] = $this->projectDir . '/' . $path;
            }
        }

        $conn = Connection::create($config);
        $this->connections[$tenantId] = $conn;

        return $conn;
    }

    /**
     * Crée une connexion depuis un config explicite (pour le provisioning).
     */
    public function createFromConfig(array $config): Connection
    {
        if ($config['driver'] === 'sqlite' && !empty($config['path'])) {
            $path = $config['path'];
            if (!str_starts_with($path, '/') && !(strlen($path) > 2 && $path[1] === ':') && $path !== ':memory:') {
                $config['path'] = $this->projectDir . '/' . $path;
            }
        }

        return Connection::create($config);
    }
}
