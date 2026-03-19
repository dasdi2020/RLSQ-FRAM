<?php

declare(strict_types=1);

namespace App\DatabaseBuilder;

use RLSQ\Database\Connection;

/**
 * CRUD sur les meta-tables (_meta_tables, _meta_columns, _meta_relations).
 * Décrit le schéma logique avant de le matérialiser en DDL.
 */
class SchemaDefinitionService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    // ==================== TABLES ====================

    public function createTable(array $data): array
    {
        $name = $data['name'] ?? throw new \InvalidArgumentException('name requis.');
        $slug = $data['slug'] ?? $this->slugify($name);
        $displayName = $data['display_name'] ?? $name;

        $this->connection->execute(
            'INSERT INTO _meta_tables (name, display_name, description, slug, icon, sort_order) VALUES (:n, :dn, :d, :s, :i, :so)',
            ['n' => $name, 'dn' => $displayName, 'd' => $data['description'] ?? null, 's' => $slug, 'i' => $data['icon'] ?? 'table', 'so' => $data['sort_order'] ?? 0],
        );

        $id = (int) $this->connection->lastInsertId();

        // Toujours ajouter une colonne id auto-increment
        $this->createColumn($id, [
            'name' => 'id', 'display_name' => 'ID', 'type' => 'integer',
            'is_primary' => true, 'is_auto_increment' => true,
        ]);

        // Toujours ajouter created_at et updated_at
        $this->createColumn($id, ['name' => 'created_at', 'display_name' => 'Créé le', 'type' => 'datetime', 'default_value' => 'CURRENT_TIMESTAMP']);
        $this->createColumn($id, ['name' => 'updated_at', 'display_name' => 'Modifié le', 'type' => 'datetime', 'default_value' => 'CURRENT_TIMESTAMP']);

        return $this->getTable($id);
    }

    public function getTable(int $id): ?array
    {
        $table = $this->connection->fetchOne('SELECT * FROM _meta_tables WHERE id = :id', ['id' => $id]);
        if (!$table) {
            return null;
        }

        $table['columns'] = $this->getColumns($id);
        $table['relations'] = $this->getRelations($id);

        return $table;
    }

    public function getTableByName(string $name): ?array
    {
        $table = $this->connection->fetchOne('SELECT * FROM _meta_tables WHERE name = :n', ['n' => $name]);
        if (!$table) {
            return null;
        }

        $table['columns'] = $this->getColumns((int) $table['id']);
        $table['relations'] = $this->getRelations((int) $table['id']);

        return $table;
    }

    /** @return array[] */
    public function getAllTables(): array
    {
        $tables = $this->connection->fetchAll('SELECT * FROM _meta_tables ORDER BY sort_order, name');

        foreach ($tables as &$t) {
            $t['columns'] = $this->getColumns((int) $t['id']);
            $t['relations'] = $this->getRelations((int) $t['id']);
        }

        return $tables;
    }

    public function updateTable(int $id, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['display_name', 'description', 'icon', 'sort_order'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }

        if (!empty($sets)) {
            $sets[] = "updated_at = :now";
            $params['now'] = date('Y-m-d H:i:s');
            $this->connection->execute('UPDATE _meta_tables SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        return $this->getTable($id);
    }

    public function deleteTable(int $id): void
    {
        $this->connection->execute('DELETE FROM _meta_tables WHERE id = :id AND is_system = 0', ['id' => $id]);
    }

    // ==================== COLUMNS ====================

    public function createColumn(int $tableId, array $data): array
    {
        $name = $data['name'] ?? throw new \InvalidArgumentException('name requis.');

        $this->connection->execute(
            'INSERT INTO _meta_columns (table_id, name, display_name, type, length, is_nullable, is_unique, is_indexed, is_primary, is_auto_increment, default_value, validation_rules, sort_order)
             VALUES (:tid, :n, :dn, :t, :l, :nu, :u, :ix, :pk, :ai, :dv, :vr, :so)',
            [
                'tid' => $tableId, 'n' => $name,
                'dn' => $data['display_name'] ?? $name,
                't' => $data['type'] ?? 'string',
                'l' => $data['length'] ?? 255,
                'nu' => ($data['is_nullable'] ?? false) ? 1 : 0,
                'u' => ($data['is_unique'] ?? false) ? 1 : 0,
                'ix' => ($data['is_indexed'] ?? false) ? 1 : 0,
                'pk' => ($data['is_primary'] ?? false) ? 1 : 0,
                'ai' => ($data['is_auto_increment'] ?? false) ? 1 : 0,
                'dv' => $data['default_value'] ?? null,
                'vr' => json_encode($data['validation_rules'] ?? []),
                'so' => $data['sort_order'] ?? 0,
            ],
        );

        $id = (int) $this->connection->lastInsertId();

        return $this->connection->fetchOne('SELECT * FROM _meta_columns WHERE id = :id', ['id' => $id]);
    }

    /** @return array[] */
    public function getColumns(int $tableId): array
    {
        return $this->connection->fetchAll(
            'SELECT * FROM _meta_columns WHERE table_id = :tid ORDER BY sort_order, id',
            ['tid' => $tableId],
        );
    }

    public function updateColumn(int $columnId, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $columnId];
        $allowed = ['display_name', 'type', 'length', 'is_nullable', 'is_unique', 'is_indexed', 'default_value', 'validation_rules', 'sort_order'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $val = $data[$f];
                if ($f === 'validation_rules' && is_array($val)) {
                    $val = json_encode($val);
                }
                if (in_array($f, ['is_nullable', 'is_unique', 'is_indexed'], true)) {
                    $val = $val ? 1 : 0;
                }
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $val;
            }
        }

        if (!empty($sets)) {
            $sets[] = "updated_at = :now";
            $params['now'] = date('Y-m-d H:i:s');
            $this->connection->execute('UPDATE _meta_columns SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        return $this->connection->fetchOne('SELECT * FROM _meta_columns WHERE id = :id', ['id' => $columnId]) ?: null;
    }

    public function deleteColumn(int $columnId): void
    {
        // Ne pas supprimer id, created_at, updated_at
        $col = $this->connection->fetchOne('SELECT * FROM _meta_columns WHERE id = :id', ['id' => $columnId]);
        if ($col && in_array($col['name'], ['id', 'created_at', 'updated_at'], true)) {
            throw new \RuntimeException('Impossible de supprimer une colonne système.');
        }

        $this->connection->execute('DELETE FROM _meta_columns WHERE id = :id', ['id' => $columnId]);
    }

    // ==================== RELATIONS ====================

    public function createRelation(array $data): array
    {
        $this->connection->execute(
            'INSERT INTO _meta_relations (source_table_id, target_table_id, type, source_column, target_column, pivot_table, on_delete, display_name)
             VALUES (:st, :tt, :t, :sc, :tc, :pt, :od, :dn)',
            [
                'st' => $data['source_table_id'], 'tt' => $data['target_table_id'],
                't' => $data['type'] ?? 'one_to_many',
                'sc' => $data['source_column'] ?? null,
                'tc' => $data['target_column'] ?? null,
                'pt' => $data['pivot_table'] ?? null,
                'od' => $data['on_delete'] ?? 'cascade',
                'dn' => $data['display_name'] ?? null,
            ],
        );

        $id = (int) $this->connection->lastInsertId();

        return $this->connection->fetchOne('SELECT * FROM _meta_relations WHERE id = :id', ['id' => $id]);
    }

    /** @return array[] */
    public function getRelations(int $tableId): array
    {
        return $this->connection->fetchAll(
            'SELECT r.*, st.name as source_table_name, tt.name as target_table_name
             FROM _meta_relations r
             JOIN _meta_tables st ON st.id = r.source_table_id
             JOIN _meta_tables tt ON tt.id = r.target_table_id
             WHERE r.source_table_id = :tid OR r.target_table_id = :tid2',
            ['tid' => $tableId, 'tid2' => $tableId],
        );
    }

    public function deleteRelation(int $relationId): void
    {
        $this->connection->execute('DELETE FROM _meta_relations WHERE id = :id', ['id' => $relationId]);
    }

    private function slugify(string $t): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($t)), '_');
    }
}
