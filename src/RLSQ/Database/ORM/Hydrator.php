<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM;

use RLSQ\Database\ORM\Mapping\ClassMetadata;

/**
 * Transforme des lignes SQL en objets entités et vice-versa.
 */
class Hydrator
{
    /**
     * Hydrate un objet entité depuis une ligne SQL.
     */
    public function hydrate(array $row, ClassMetadata $meta): object
    {
        $ref = new \ReflectionClass($meta->className);
        $entity = $ref->newInstanceWithoutConstructor();

        foreach ($meta->columns as $property => $info) {
            $column = $info['column'];
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $value = $this->castValue($row[$column], $info['type']);

            $prop = $ref->getProperty($property);
            $prop->setValue($entity, $value);
        }

        return $entity;
    }

    /**
     * Extrait les données d'un objet entité en tableau colonne → valeur.
     */
    public function dehydrate(object $entity, ClassMetadata $meta, bool $includeId = false): array
    {
        $ref = new \ReflectionClass($meta->className);
        $data = [];

        foreach ($meta->columns as $property => $info) {
            if (!$includeId && $property === $meta->idProperty && $meta->idGenerated) {
                continue;
            }

            $prop = $ref->getProperty($property);
            $value = $prop->getValue($entity);
            $data[$info['column']] = $value;
        }

        return $data;
    }

    /**
     * Retourne la valeur de l'identifiant d'une entité.
     */
    public function getIdValue(object $entity, ClassMetadata $meta): mixed
    {
        if ($meta->idProperty === null) {
            return null;
        }

        $ref = new \ReflectionClass($meta->className);
        $prop = $ref->getProperty($meta->idProperty);

        if (!$prop->isInitialized($entity)) {
            return null;
        }

        return $prop->getValue($entity);
    }

    /**
     * Définit la valeur de l'identifiant sur une entité.
     */
    public function setIdValue(object $entity, ClassMetadata $meta, mixed $value): void
    {
        if ($meta->idProperty === null) {
            return;
        }

        $ref = new \ReflectionClass($meta->className);
        $prop = $ref->getProperty($meta->idProperty);
        $prop->setValue($entity, $this->castValue($value, $meta->columns[$meta->idProperty]['type'] ?? 'integer'));
    }

    private function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'decimal' => (float) $value,
            'boolean', 'bool' => (bool) $value,
            'datetime' => new \DateTimeImmutable((string) $value),
            default => (string) $value,
        };
    }
}
