<?php

declare(strict_types=1);

namespace RLSQ\Database;

/**
 * Construction de requêtes SQL de manière programmatique.
 */
class QueryBuilder
{
    private ?string $type = null;
    private array $select = [];
    private ?string $from = null;
    private ?string $fromAlias = null;
    private array $joins = [];
    private array $where = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $groupBy = [];
    private array $having = [];
    private array $parameters = [];
    private array $set = [];
    private array $insertColumns = [];
    private array $insertValues = [];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    // --- SELECT ---

    public function select(string ...$columns): static
    {
        $this->type = 'SELECT';
        $this->select = $columns ?: ['*'];

        return $this;
    }

    public function addSelect(string ...$columns): static
    {
        $this->select = array_merge($this->select, $columns);

        return $this;
    }

    public function from(string $table, ?string $alias = null): static
    {
        $this->from = $table;
        $this->fromAlias = $alias;

        return $this;
    }

    // --- INSERT ---

    public function insert(string $table): static
    {
        $this->type = 'INSERT';
        $this->from = $table;

        return $this;
    }

    public function values(array $values): static
    {
        $this->insertColumns = array_keys($values);
        $this->insertValues = array_values($values);

        return $this;
    }

    // --- UPDATE ---

    public function update(string $table, ?string $alias = null): static
    {
        $this->type = 'UPDATE';
        $this->from = $table;
        $this->fromAlias = $alias;

        return $this;
    }

    public function set(string $column, mixed $value): static
    {
        $this->set[] = [$column, $value];

        return $this;
    }

    // --- DELETE ---

    public function delete(string $table, ?string $alias = null): static
    {
        $this->type = 'DELETE';
        $this->from = $table;
        $this->fromAlias = $alias;

        return $this;
    }

    // --- JOIN ---

    public function join(string $table, string $alias, string $condition): static
    {
        $this->joins[] = ['INNER JOIN', $table, $alias, $condition];

        return $this;
    }

    public function leftJoin(string $table, string $alias, string $condition): static
    {
        $this->joins[] = ['LEFT JOIN', $table, $alias, $condition];

        return $this;
    }

    // --- WHERE ---

    public function where(string $condition): static
    {
        $this->where = [$condition];

        return $this;
    }

    public function andWhere(string $condition): static
    {
        $this->where[] = $condition;

        return $this;
    }

    public function orWhere(string $condition): static
    {
        if (empty($this->where)) {
            $this->where[] = $condition;
        } else {
            $last = array_pop($this->where);
            $this->where[] = '(' . $last . ' OR ' . $condition . ')';
        }

        return $this;
    }

    // --- ORDER / LIMIT / GROUP ---

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy = [[$column, strtoupper($direction)]];

        return $this;
    }

    public function addOrderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy[] = [$column, strtoupper($direction)];

        return $this;
    }

    public function setMaxResults(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function setFirstResult(?int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->groupBy = $columns;

        return $this;
    }

    public function having(string $condition): static
    {
        $this->having = [$condition];

        return $this;
    }

    // --- Parameters ---

    public function setParameter(string $key, mixed $value): static
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    // --- Build SQL ---

    public function getSQL(): string
    {
        return match ($this->type) {
            'SELECT' => $this->buildSelect(),
            'INSERT' => $this->buildInsert(),
            'UPDATE' => $this->buildUpdate(),
            'DELETE' => $this->buildDelete(),
            default => throw new \LogicException('Aucun type de requête défini. Appelez select(), insert(), update() ou delete().'),
        };
    }

    private function buildSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->select);

        $sql .= ' FROM ' . $this->from;
        if ($this->fromAlias) {
            $sql .= ' ' . $this->fromAlias;
        }

        foreach ($this->joins as [$type, $table, $alias, $condition]) {
            $sql .= ' ' . $type . ' ' . $table . ' ' . $alias . ' ON ' . $condition;
        }

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if (!empty($this->having)) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
        }

        if (!empty($this->orderBy)) {
            $parts = array_map(fn ($o) => $o[0] . ' ' . $o[1], $this->orderBy);
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    private function buildInsert(): string
    {
        $cols = implode(', ', $this->insertColumns);
        $placeholders = implode(', ', array_map(fn ($c) => ':' . $c, $this->insertColumns));

        return 'INSERT INTO ' . $this->from . ' (' . $cols . ') VALUES (' . $placeholders . ')';
    }

    private function buildUpdate(): string
    {
        $sql = 'UPDATE ' . $this->from;
        if ($this->fromAlias) {
            $sql .= ' ' . $this->fromAlias;
        }

        $setParts = array_map(fn ($s) => $s[0] . ' = ' . $s[1], $this->set);
        $sql .= ' SET ' . implode(', ', $setParts);

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        return $sql;
    }

    private function buildDelete(): string
    {
        $sql = 'DELETE FROM ' . $this->from;

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        return $sql;
    }

    // --- Execute ---

    public function executeQuery(): \PDOStatement
    {
        return $this->connection->execute($this->getSQL(), $this->parameters);
    }

    public function executeStatement(): int
    {
        $stmt = $this->connection->execute($this->getSQL(), $this->parameters);

        return $stmt->rowCount();
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function fetchAllAssociative(): array
    {
        return $this->executeQuery()->fetchAll();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function fetchAssociative(): array|false
    {
        return $this->executeQuery()->fetch();
    }
}
