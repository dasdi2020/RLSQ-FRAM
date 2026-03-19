<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM;

use RLSQ\Database\Connection;
use RLSQ\Database\ORM\Mapping\AttributeReader;
use RLSQ\Database\ORM\Mapping\ClassMetadata;

/**
 * Suivi des entités : new, managed, dirty, removed.
 * Calcule les changesets au flush().
 */
class UnitOfWork
{
    /** @var array<string, array<int|string, object>> className => [id => entity] */
    private array $identityMap = [];

    /** @var \SplObjectStorage<object, true> Entités marquées pour insertion */
    private \SplObjectStorage $scheduledInsertions;

    /** @var \SplObjectStorage<object, true> Entités marquées pour suppression */
    private \SplObjectStorage $scheduledDeletions;

    /** @var \SplObjectStorage<object, array<string, mixed>> Snapshots originaux */
    private \SplObjectStorage $originalData;

    private Hydrator $hydrator;

    public function __construct(
        private readonly Connection $connection,
        private readonly AttributeReader $metadataReader,
    ) {
        $this->scheduledInsertions = new \SplObjectStorage();
        $this->scheduledDeletions = new \SplObjectStorage();
        $this->originalData = new \SplObjectStorage();
        $this->hydrator = new Hydrator();
    }

    /**
     * Marque une entité pour insertion.
     */
    public function persist(object $entity): void
    {
        $meta = $this->metadataReader->getClassMetadata($entity::class);
        $id = $this->hydrator->getIdValue($entity, $meta);

        // Déjà managée ?
        if ($id !== null && isset($this->identityMap[$meta->className][$id])) {
            return;
        }

        $this->scheduledInsertions->attach($entity);
    }

    /**
     * Marque une entité pour suppression.
     */
    public function remove(object $entity): void
    {
        $this->scheduledDeletions->attach($entity);
    }

    /**
     * Enregistre une entité comme managée (chargée depuis la DB).
     */
    public function registerManaged(object $entity, ClassMetadata $meta): void
    {
        $id = $this->hydrator->getIdValue($entity, $meta);

        if ($id !== null) {
            $this->identityMap[$meta->className][$id] = $entity;
            // Sauvegarder le snapshot pour la détection de changements
            $this->originalData->attach($entity, $this->hydrator->dehydrate($entity, $meta, true));
        }
    }

    /**
     * Flush : exécute toutes les opérations en attente.
     */
    public function flush(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->executeInsertions();
            $this->executeUpdates();
            $this->executeDeletions();

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Recherche dans l'identity map.
     */
    public function tryGetById(string $className, int|string $id): ?object
    {
        return $this->identityMap[$className][$id] ?? null;
    }

    private function executeInsertions(): void
    {
        foreach ($this->scheduledInsertions as $entity) {
            $meta = $this->metadataReader->getClassMetadata($entity::class);
            $data = $this->hydrator->dehydrate($entity, $meta);

            $columns = array_keys($data);
            $placeholders = array_map(fn ($c) => ':' . $c, $columns);

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $meta->tableName,
                implode(', ', $columns),
                implode(', ', $placeholders),
            );

            $this->connection->execute($sql, $data);

            // Mettre à jour l'ID auto-généré
            if ($meta->idGenerated) {
                $newId = $this->connection->lastInsertId();
                $this->hydrator->setIdValue($entity, $meta, $newId);
            }

            // Enregistrer comme managée
            $this->registerManaged($entity, $meta);
        }

        $this->scheduledInsertions = new \SplObjectStorage();
    }

    private function executeUpdates(): void
    {
        foreach ($this->identityMap as $className => $entities) {
            $meta = $this->metadataReader->getClassMetadata($className);

            foreach ($entities as $id => $entity) {
                if ($this->scheduledDeletions->contains($entity)) {
                    continue;
                }

                $currentData = $this->hydrator->dehydrate($entity, $meta, true);

                $originalData = $this->originalData->contains($entity)
                    ? $this->originalData[$entity]
                    : [];

                $changeSet = array_diff_assoc($currentData, $originalData);

                if (empty($changeSet)) {
                    continue;
                }

                $setParts = [];
                $params = [];

                foreach ($changeSet as $column => $value) {
                    $setParts[] = $column . ' = :set_' . $column;
                    $params['set_' . $column] = $value;
                }

                $params['id'] = $id;

                $sql = sprintf(
                    'UPDATE %s SET %s WHERE %s = :id',
                    $meta->tableName,
                    implode(', ', $setParts),
                    $meta->idColumn,
                );

                $this->connection->execute($sql, $params);

                // Mettre à jour le snapshot
                $this->originalData->attach($entity, $currentData);
            }
        }
    }

    private function executeDeletions(): void
    {
        foreach ($this->scheduledDeletions as $entity) {
            $meta = $this->metadataReader->getClassMetadata($entity::class);
            $id = $this->hydrator->getIdValue($entity, $meta);

            $sql = sprintf('DELETE FROM %s WHERE %s = :id', $meta->tableName, $meta->idColumn);
            $this->connection->execute($sql, ['id' => $id]);

            // Retirer de l'identity map
            unset($this->identityMap[$meta->className][$id]);
            if ($this->originalData->contains($entity)) {
                $this->originalData->detach($entity);
            }
        }

        $this->scheduledDeletions = new \SplObjectStorage();
    }
}
