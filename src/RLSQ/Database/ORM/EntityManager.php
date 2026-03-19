<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM;

use RLSQ\Database\Connection;
use RLSQ\Database\ORM\Mapping\AttributeReader;
use RLSQ\Database\ORM\Mapping\ClassMetadata;
use RLSQ\Database\QueryBuilder;

class EntityManager
{
    private AttributeReader $metadataReader;
    private UnitOfWork $unitOfWork;
    /** @var array<string, EntityRepository> */
    private array $repositories = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
        $this->metadataReader = new AttributeReader();
        $this->unitOfWork = new UnitOfWork($connection, $this->metadataReader);
    }

    public function persist(object $entity): void
    {
        $this->unitOfWork->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->unitOfWork->remove($entity);
    }

    public function flush(): void
    {
        $this->unitOfWork->flush();
    }

    public function find(string $className, int|string $id): ?object
    {
        return $this->getRepository($className)->find($id);
    }

    public function getRepository(string $className): EntityRepository
    {
        if (!isset($this->repositories[$className])) {
            $meta = $this->metadataReader->getClassMetadata($className);
            $this->repositories[$className] = new EntityRepository(
                $this->connection,
                $meta,
                $this->unitOfWork,
            );
        }

        return $this->repositories[$className];
    }

    public function getClassMetadata(string $className): ClassMetadata
    {
        return $this->metadataReader->getClassMetadata($className);
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }

    /**
     * Crée les tables depuis les métadonnées d'entités.
     *
     * @param string[] $entityClasses
     */
    public function createSchema(array $entityClasses): void
    {
        foreach ($entityClasses as $class) {
            $meta = $this->metadataReader->getClassMetadata($class);
            $sql = $this->buildCreateTableSQL($meta);
            $this->connection->exec($sql);
        }
    }

    private function buildCreateTableSQL(ClassMetadata $meta): string
    {
        $columns = [];

        foreach ($meta->columns as $property => $info) {
            $colDef = $info['column'] . ' ' . $this->mapColumnType($info['type'], $info['length'] ?? 255);

            if ($property === $meta->idProperty) {
                $colDef .= ' PRIMARY KEY';
                if ($meta->idGenerated) {
                    $colDef .= ' AUTOINCREMENT';
                }
            }

            if (!$info['nullable'] && $property !== $meta->idProperty) {
                $colDef .= ' NOT NULL';
            }

            if ($info['unique'] ?? false) {
                $colDef .= ' UNIQUE';
            }

            $columns[] = $colDef;
        }

        // Colonnes de clé étrangère pour ManyToOne
        foreach ($meta->manyToOne as $property => $info) {
            $columns[] = $info['joinColumn'] . ' INTEGER';
        }

        return sprintf(
            'CREATE TABLE IF NOT EXISTS %s (%s)',
            $meta->tableName,
            implode(', ', $columns),
        );
    }

    private function mapColumnType(string $type, int $length): string
    {
        return match ($type) {
            'integer', 'int' => 'INTEGER',
            'string' => 'VARCHAR(' . $length . ')',
            'text' => 'TEXT',
            'float', 'decimal' => 'REAL',
            'boolean', 'bool' => 'BOOLEAN',
            'datetime' => 'DATETIME',
            default => 'TEXT',
        };
    }
}
