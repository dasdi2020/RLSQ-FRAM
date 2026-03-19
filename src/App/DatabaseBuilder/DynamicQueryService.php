<?php

declare(strict_types=1);

namespace App\DatabaseBuilder;

use RLSQ\Database\Connection;

/**
 * CRUD dynamique sur les tables créées par le schema builder.
 * Supporte filtres, tri, pagination, inclusion de relations.
 */
class DynamicQueryService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaDefinitionService $schemaDef,
    ) {}

    /**
     * Liste les enregistrements d'une table avec filtres, tri, pagination.
     *
     * @param array $options [
     *   'filter' => ['column' => 'value', 'column' => ['gte' => 10]],
     *   'sort' => '-created_at' (prefix - = DESC),
     *   'page' => 1,
     *   'per_page' => 20,
     *   'include' => 'relation1,relation2',
     *   'search' => 'keyword',
     *   'search_columns' => ['name', 'email'],
     * ]
     */
    public function findAll(string $tableName, array $options = []): array
    {
        $table = $this->schemaDef->getTableByName($tableName);
        if ($table === null) {
            throw new \RuntimeException("Table '{$tableName}' introuvable.");
        }

        $page = max(1, (int) ($options['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($options['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        $this->buildFilters($options['filter'] ?? [], $where, $params);
        $this->buildSearch($options['search'] ?? '', $options['search_columns'] ?? [], $table['columns'], $where, $params);

        $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $orderSql = $this->buildSort($options['sort'] ?? '-created_at');

        // Count total
        $total = (int) $this->connection->fetchColumn(
            "SELECT COUNT(*) FROM \"{$tableName}\"{$whereSql}",
            $params,
        );

        // Fetch data
        $rows = $this->connection->fetchAll(
            "SELECT * FROM \"{$tableName}\"{$whereSql}{$orderSql} LIMIT :_limit OFFSET :_offset",
            array_merge($params, ['_limit' => $perPage, '_offset' => $offset]),
        );

        return [
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Récupère un enregistrement par ID.
     */
    public function find(string $tableName, int $id): ?array
    {
        $row = $this->connection->fetchOne(
            "SELECT * FROM \"{$tableName}\" WHERE id = :id",
            ['id' => $id],
        );

        return $row ?: null;
    }

    /**
     * Crée un enregistrement.
     */
    public function create(string $tableName, array $data): array
    {
        $table = $this->schemaDef->getTableByName($tableName);
        if ($table === null) {
            throw new \RuntimeException("Table '{$tableName}' introuvable.");
        }

        // Valider et filtrer les colonnes
        $validColumns = $this->getWritableColumns($table['columns']);
        $filtered = $this->filterData($data, $validColumns);

        // Ajouter timestamps
        $filtered['created_at'] = date('Y-m-d H:i:s');
        $filtered['updated_at'] = date('Y-m-d H:i:s');

        // Validation
        $errors = $this->validate($filtered, $table['columns']);
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $columns = array_keys($filtered);
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);

        $this->connection->execute(
            sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $tableName, implode(', ', $columns), implode(', ', $placeholders)),
            $filtered,
        );

        $id = (int) $this->connection->lastInsertId();

        return $this->find($tableName, $id);
    }

    /**
     * Met à jour un enregistrement.
     */
    public function update(string $tableName, int $id, array $data): ?array
    {
        $table = $this->schemaDef->getTableByName($tableName);
        if ($table === null) {
            throw new \RuntimeException("Table '{$tableName}' introuvable.");
        }

        $validColumns = $this->getWritableColumns($table['columns']);
        $filtered = $this->filterData($data, $validColumns);
        $filtered['updated_at'] = date('Y-m-d H:i:s');

        $errors = $this->validate($filtered, $table['columns']);
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $sets = [];
        $params = ['_id' => $id];

        foreach ($filtered as $col => $val) {
            $sets[] = "\"{$col}\" = :{$col}";
            $params[$col] = $val;
        }

        $this->connection->execute(
            sprintf('UPDATE "%s" SET %s WHERE id = :_id', $tableName, implode(', ', $sets)),
            $params,
        );

        return $this->find($tableName, $id);
    }

    /**
     * Supprime un enregistrement.
     */
    public function delete(string $tableName, int $id): bool
    {
        $stmt = $this->connection->execute(
            "DELETE FROM \"{$tableName}\" WHERE id = :id",
            ['id' => $id],
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Compte les enregistrements.
     */
    public function count(string $tableName, array $filters = []): int
    {
        $where = [];
        $params = [];
        $this->buildFilters($filters, $where, $params);
        $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        return (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM \"{$tableName}\"{$whereSql}", $params);
    }

    /**
     * Export tous les enregistrements (sans pagination).
     */
    public function export(string $tableName): array
    {
        return $this->connection->fetchAll("SELECT * FROM \"{$tableName}\" ORDER BY id ASC");
    }

    // ==================== PRIVATE ====================

    private function buildFilters(array $filters, array &$where, array &$params): void
    {
        foreach ($filters as $col => $val) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);

            if (is_array($val)) {
                foreach ($val as $op => $opVal) {
                    $paramKey = "f_{$safeCol}_{$op}";
                    $sqlOp = match ($op) {
                        'eq' => '=', 'neq' => '!=',
                        'gt' => '>', 'gte' => '>=',
                        'lt' => '<', 'lte' => '<=',
                        'like' => 'LIKE',
                        default => '=',
                    };
                    $where[] = "\"{$safeCol}\" {$sqlOp} :{$paramKey}";
                    $params[$paramKey] = $op === 'like' ? "%{$opVal}%" : $opVal;
                }
            } else {
                $where[] = "\"{$safeCol}\" = :f_{$safeCol}";
                $params["f_{$safeCol}"] = $val;
            }
        }
    }

    private function buildSearch(string $search, array $searchCols, array $allColumns, array &$where, array &$params): void
    {
        if ($search === '') {
            return;
        }

        // Si pas de colonnes spécifiées, chercher dans les colonnes string/text/email
        if (empty($searchCols)) {
            foreach ($allColumns as $col) {
                if (in_array($col['type'], ['string', 'text', 'email', 'url', 'phone'], true)) {
                    $searchCols[] = $col['name'];
                }
            }
        }

        if (empty($searchCols)) {
            return;
        }

        $orClauses = [];
        foreach ($searchCols as $i => $col) {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            $orClauses[] = "\"{$safe}\" LIKE :_search{$i}";
            $params["_search{$i}"] = "%{$search}%";
        }

        $where[] = '(' . implode(' OR ', $orClauses) . ')';
    }

    private function buildSort(string $sort): string
    {
        if ($sort === '') {
            return '';
        }

        $parts = explode(',', $sort);
        $clauses = [];

        foreach ($parts as $s) {
            $s = trim($s);
            if (str_starts_with($s, '-')) {
                $clauses[] = '"' . substr($s, 1) . '" DESC';
            } else {
                $clauses[] = '"' . $s . '" ASC';
            }
        }

        return ' ORDER BY ' . implode(', ', $clauses);
    }

    private function getWritableColumns(array $columns): array
    {
        $writable = [];
        foreach ($columns as $col) {
            if (!$col['is_primary'] && !$col['is_auto_increment'] && !in_array($col['name'], ['created_at', 'updated_at'], true)) {
                $writable[] = $col['name'];
            }
        }

        return $writable;
    }

    private function filterData(array $data, array $validColumns): array
    {
        return array_intersect_key($data, array_flip($validColumns));
    }

    private function validate(array $data, array $columns): array
    {
        $errors = [];

        foreach ($columns as $col) {
            $name = $col['name'];
            if (in_array($name, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $rules = json_decode($col['validation_rules'] ?? '{}', true) ?: [];
            $value = $data[$name] ?? null;

            if (!empty($rules['required']) && ($value === null || $value === '')) {
                $errors[$name][] = sprintf('Le champ "%s" est requis.', $col['display_name']);
            }

            if (!empty($rules['min_length']) && is_string($value) && mb_strlen($value) < $rules['min_length']) {
                $errors[$name][] = sprintf('%s : minimum %d caractères.', $col['display_name'], $rules['min_length']);
            }

            if (!empty($rules['max_length']) && is_string($value) && mb_strlen($value) > $rules['max_length']) {
                $errors[$name][] = sprintf('%s : maximum %d caractères.', $col['display_name'], $rules['max_length']);
            }

            if (!empty($rules['email']) && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name][] = sprintf('%s : email invalide.', $col['display_name']);
            }
        }

        return $errors;
    }
}

class ValidationException extends \RuntimeException
{
    public function __construct(
        public readonly array $errors,
        string $message = 'Validation échouée.',
    ) {
        parent::__construct($message);
    }
}
