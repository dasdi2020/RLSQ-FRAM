<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM;

use RLSQ\Database\Connection;
use RLSQ\Database\ORM\Mapping\ClassMetadata;

class EntityRepository
{
    private Hydrator $hydrator;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClassMetadata $meta,
        private readonly UnitOfWork $unitOfWork,
    ) {
        $this->hydrator = new Hydrator();
    }

    public function find(int|string $id): ?object
    {
        // Identity map first
        $cached = $this->unitOfWork->tryGetById($this->meta->className, $id);
        if ($cached !== null) {
            return $cached;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :id LIMIT 1',
            $this->meta->tableName,
            $this->meta->idColumn,
        );

        $row = $this->connection->fetchOne($sql, ['id' => $id]);

        if ($row === false) {
            return null;
        }

        return $this->hydrateAndManage($row);
    }

    /**
     * @return object[]
     */
    public function findAll(): array
    {
        $sql = sprintf('SELECT * FROM %s', $this->meta->tableName);
        $rows = $this->connection->fetchAll($sql);

        return array_map(fn ($row) => $this->hydrateAndManage($row), $rows);
    }

    /**
     * @param array<string, mixed> $criteria Propriété => valeur
     * @param array<string, string>|null $orderBy Propriété => ASC/DESC
     * @return object[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        [$whereSql, $params] = $this->buildWhere($criteria);

        $sql = sprintf('SELECT * FROM %s', $this->meta->tableName);

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        if ($orderBy !== null) {
            $parts = [];
            foreach ($orderBy as $prop => $dir) {
                $col = $this->meta->getColumnForProperty($prop) ?? $prop;
                $parts[] = $col . ' ' . strtoupper($dir);
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }

        $rows = $this->connection->fetchAll($sql, $params);

        return array_map(fn ($row) => $this->hydrateAndManage($row), $rows);
    }

    public function findOneBy(array $criteria): ?object
    {
        $result = $this->findBy($criteria, null, 1);

        return $result[0] ?? null;
    }

    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            $sql = sprintf('SELECT COUNT(*) FROM %s', $this->meta->tableName);
            return (int) $this->connection->fetchColumn($sql);
        }

        [$whereSql, $params] = $this->buildWhere($criteria);
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s', $this->meta->tableName, $whereSql);

        return (int) $this->connection->fetchColumn($sql, $params);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $criteria): array
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $property => $value) {
            $column = $this->meta->getColumnForProperty($property) ?? $property;

            if ($value === null) {
                $conditions[] = $column . ' IS NULL';
            } else {
                $conditions[] = $column . ' = :' . $property;
                $params[$property] = $value;
            }
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function hydrateAndManage(array $row): object
    {
        $entity = $this->hydrator->hydrate($row, $this->meta);
        $this->unitOfWork->registerManaged($entity, $this->meta);

        return $entity;
    }
}
