<?php

declare(strict_types=1);

namespace App\DatabaseBuilder;

use RLSQ\Database\Connection;

/**
 * Synchronise les meta-tables avec les tables réelles (DDL).
 * Crée, modifie et supprime les tables physiques.
 */
class DynamicSchemaManager
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaDefinitionService $schemaDef,
    ) {}

    /**
     * Crée la table physique correspondant à une meta-table.
     */
    public function createPhysicalTable(int $tableId): void
    {
        $table = $this->schemaDef->getTable($tableId);
        if ($table === null) {
            throw new \RuntimeException("Meta-table {$tableId} introuvable.");
        }

        $columns = $table['columns'] ?? [];
        $colDefs = [];

        foreach ($columns as $col) {
            $colDefs[] = $this->buildColumnDDL($col);
        }

        // Foreign keys pour les relations
        foreach (($table['relations'] ?? []) as $rel) {
            if ($rel['type'] === 'many_to_one' && (int) $rel['source_table_id'] === $tableId) {
                $fkCol = $rel['source_column'] ?? $rel['target_table_name'] . '_id';
                // Ajouter la colonne FK si pas déjà dans les colonnes
                $existing = array_column($columns, 'name');
                if (!in_array($fkCol, $existing, true)) {
                    $colDefs[] = "{$fkCol} INTEGER";
                }
            }
        }

        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (%s)',
            $this->quote($table['name']),
            implode(', ', $colDefs),
        );

        $this->connection->exec($sql);
    }

    /**
     * Ajoute une colonne physique à une table existante.
     */
    public function addPhysicalColumn(string $tableName, array $column): void
    {
        $colDDL = $this->buildColumnDDL($column);
        $sql = sprintf('ALTER TABLE %s ADD COLUMN %s', $this->quote($tableName), $colDDL);
        $this->connection->exec($sql);
    }

    /**
     * Supprime une table physique.
     */
    public function dropPhysicalTable(string $tableName): void
    {
        $this->connection->exec(sprintf('DROP TABLE IF EXISTS %s', $this->quote($tableName)));
    }

    /**
     * Crée la table pivot pour une relation many-to-many.
     */
    public function createPivotTable(string $pivotName, string $table1, string $table2): void
    {
        $col1 = $table1 . '_id';
        $col2 = $table2 . '_id';

        $this->connection->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (id INTEGER PRIMARY KEY AUTOINCREMENT, %s INTEGER NOT NULL, %s INTEGER NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(%s, %s))',
            $this->quote($pivotName),
            $col1, $col2, $col1, $col2,
        ));
    }

    /**
     * Synchronise toutes les meta-tables → tables physiques.
     */
    public function syncAll(): array
    {
        $tables = $this->schemaDef->getAllTables();
        $results = [];

        foreach ($tables as $table) {
            $exists = $this->tableExists($table['name']);

            if (!$exists) {
                $this->createPhysicalTable((int) $table['id']);
                $results[] = ['table' => $table['name'], 'action' => 'created'];
            }
            // TODO: ALTER pour les colonnes ajoutées après la création
        }

        // Créer les tables pivot pour many_to_many
        foreach ($tables as $table) {
            foreach (($table['relations'] ?? []) as $rel) {
                if ($rel['type'] === 'many_to_many' && !empty($rel['pivot_table'])) {
                    if (!$this->tableExists($rel['pivot_table'])) {
                        $this->createPivotTable($rel['pivot_table'], $rel['source_table_name'], $rel['target_table_name']);
                        $results[] = ['table' => $rel['pivot_table'], 'action' => 'pivot_created'];
                    }
                }
            }
        }

        return $results;
    }

    public function tableExists(string $name): bool
    {
        $row = $this->connection->fetchOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = :n",
            ['n' => $name],
        );

        return $row !== false;
    }

    private function buildColumnDDL(array $col): string
    {
        $name = $col['name'];
        $type = $this->mapType($col['type'], (int) ($col['length'] ?? 255));
        $ddl = "{$name} {$type}";

        if (!empty($col['is_primary'])) {
            $ddl .= ' PRIMARY KEY';
            if (!empty($col['is_auto_increment'])) {
                $ddl .= ' AUTOINCREMENT';
            }
        }

        if (empty($col['is_primary']) && empty($col['is_nullable'])) {
            // SQLite ne supporte pas NOT NULL sur ALTER TABLE, on le met seulement au CREATE
        }

        if (!empty($col['is_unique']) && empty($col['is_primary'])) {
            $ddl .= ' UNIQUE';
        }

        if (isset($col['default_value']) && $col['default_value'] !== null && $col['default_value'] !== '') {
            $dv = $col['default_value'];
            if ($dv === 'CURRENT_TIMESTAMP') {
                $ddl .= ' DEFAULT CURRENT_TIMESTAMP';
            } elseif (is_numeric($dv)) {
                $ddl .= ' DEFAULT ' . $dv;
            } else {
                $ddl .= " DEFAULT '" . str_replace("'", "''", $dv) . "'";
            }
        }

        return $ddl;
    }

    private function mapType(string $type, int $length): string
    {
        return match ($type) {
            'integer', 'int' => 'INTEGER',
            'float', 'decimal', 'number' => 'REAL',
            'boolean', 'bool' => 'BOOLEAN',
            'text', 'richtext' => 'TEXT',
            'datetime', 'date' => 'DATETIME',
            'json' => 'TEXT',
            'email', 'url', 'phone', 'file' => "VARCHAR({$length})",
            default => "VARCHAR({$length})",
        };
    }

    private function quote(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
