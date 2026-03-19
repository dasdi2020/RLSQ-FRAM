<?php

declare(strict_types=1);

namespace RLSQ\Database\Migration;

use RLSQ\Database\Connection;

class MigrationManager
{
    /** @var MigrationInterface[] */
    private array $migrations = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
        $this->ensureMigrationsTable();
    }

    public function addMigration(MigrationInterface $migration): void
    {
        $this->migrations[$migration->getVersion()] = $migration;
    }

    /**
     * @param MigrationInterface[] $migrations
     */
    public function addMigrations(array $migrations): void
    {
        foreach ($migrations as $migration) {
            $this->addMigration($migration);
        }
    }

    /**
     * Exécute les migrations non encore appliquées. Retourne le nombre exécuté.
     */
    public function migrate(): int
    {
        $executed = $this->getExecutedVersions();
        $pending = $this->getPendingMigrations($executed);

        if (empty($pending)) {
            return 0;
        }

        $count = 0;

        foreach ($pending as $migration) {
            $this->connection->beginTransaction();

            try {
                $migration->up($this->connection);

                $this->connection->execute(
                    'INSERT INTO _migrations (version, description, executed_at) VALUES (:v, :d, :t)',
                    ['v' => $migration->getVersion(), 'd' => $migration->getDescription(), 't' => date('Y-m-d H:i:s')],
                );

                $this->connection->commit();
                $count++;
            } catch (\Throwable $e) {
                $this->connection->rollback();
                throw new \RuntimeException(sprintf('Migration %s échouée : %s', $migration->getVersion(), $e->getMessage()), 0, $e);
            }
        }

        return $count;
    }

    /**
     * Rollback la dernière migration.
     */
    public function rollback(): bool
    {
        $executed = $this->getExecutedVersions();

        if (empty($executed)) {
            return false;
        }

        $lastVersion = end($executed);

        if (!isset($this->migrations[$lastVersion])) {
            throw new \RuntimeException(sprintf('Migration %s introuvable pour le rollback.', $lastVersion));
        }

        $migration = $this->migrations[$lastVersion];

        $this->connection->beginTransaction();

        try {
            $migration->down($this->connection);
            $this->connection->execute('DELETE FROM _migrations WHERE version = :v', ['v' => $lastVersion]);
            $this->connection->commit();

            return true;
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw new \RuntimeException(sprintf('Rollback %s échoué : %s', $lastVersion, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @return string[]
     */
    public function getExecutedVersions(): array
    {
        $rows = $this->connection->fetchAll('SELECT version FROM _migrations ORDER BY version ASC');

        return array_column($rows, 'version');
    }

    /**
     * @return MigrationInterface[]
     */
    public function getPendingMigrations(array $executed = []): array
    {
        if (empty($executed)) {
            $executed = $this->getExecutedVersions();
        }

        $pending = [];

        ksort($this->migrations);

        foreach ($this->migrations as $version => $migration) {
            if (!in_array($version, $executed, true)) {
                $pending[] = $migration;
            }
        }

        return $pending;
    }

    /**
     * @return array{version: string, description: string, status: string}[]
     */
    public function getStatus(): array
    {
        $executed = $this->getExecutedVersions();
        $status = [];

        ksort($this->migrations);

        foreach ($this->migrations as $version => $migration) {
            $status[] = [
                'version' => $version,
                'description' => $migration->getDescription(),
                'status' => in_array($version, $executed, true) ? 'executed' : 'pending',
            ];
        }

        return $status;
    }

    private function ensureMigrationsTable(): void
    {
        $this->connection->exec('
            CREATE TABLE IF NOT EXISTS _migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                version VARCHAR(50) NOT NULL UNIQUE,
                description VARCHAR(255) DEFAULT "",
                executed_at DATETIME NOT NULL
            )
        ');
    }
}
