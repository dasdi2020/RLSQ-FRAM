<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;
use RLSQ\Database\Migration\MigrationManager;

/**
 * Provisionne une nouvelle base de données pour un tenant.
 * Crée la DB, exécute les migrations de base tenant, insère les données initiales.
 */
class TenantDatabaseProvisioner
{
    /** @var MigrationInterface[] */
    private array $baseMigrations = [];

    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Ajoute des migrations de base exécutées pour chaque nouveau tenant.
     */
    public function addBaseMigration(MigrationInterface $migration): void
    {
        $this->baseMigrations[] = $migration;
    }

    /**
     * @param MigrationInterface[] $migrations
     */
    public function addBaseMigrations(array $migrations): void
    {
        foreach ($migrations as $m) {
            $this->baseMigrations[] = $m;
        }
    }

    /**
     * Provisionne la DB pour un tenant.
     *
     * @return array{connection: Connection, migrations_run: int}
     */
    public function provision(array $dbConfig, array $tenantData = []): array
    {
        // Résoudre le chemin SQLite
        if ($dbConfig['driver'] === 'sqlite' && !empty($dbConfig['path'])) {
            $path = $dbConfig['path'];
            if (!str_starts_with($path, '/') && !(strlen($path) > 2 && $path[1] === ':') && $path !== ':memory:') {
                $dbConfig['path'] = $this->projectDir . '/' . $path;
            }

            // Créer le dossier parent si nécessaire
            $dir = dirname($dbConfig['path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        // Créer la connexion
        $connection = Connection::create($dbConfig);

        // Exécuter les migrations de base
        $manager = new MigrationManager($connection);
        $manager->addMigrations($this->baseMigrations);
        $count = $manager->migrate();

        // Insérer les données initiales du tenant
        if (!empty($tenantData)) {
            $this->seedTenantData($connection, $tenantData);
        }

        return ['connection' => $connection, 'migrations_run' => $count];
    }

    private function seedTenantData(Connection $connection, array $data): void
    {
        // Insérer la config du tenant dans sa propre DB
        $connection->exec('
            CREATE TABLE IF NOT EXISTS _tenant_config (
                key VARCHAR(100) PRIMARY KEY,
                value TEXT
            )
        ');

        foreach ($data as $key => $value) {
            $connection->execute(
                'INSERT OR REPLACE INTO _tenant_config (key, value) VALUES (:k, :v)',
                ['k' => $key, 'v' => is_array($value) ? json_encode($value) : (string) $value],
            );
        }
    }
}
