<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM\Mapping;

/**
 * Lit les attributs PHP 8 d'une classe pour construire les ClassMetadata.
 */
class AttributeReader
{
    /** @var array<string, ClassMetadata> */
    private array $cache = [];

    public function getClassMetadata(string $className): ClassMetadata
    {
        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        $meta = new ClassMetadata($className);
        $ref = new \ReflectionClass($className);

        // Attribut #[Entity] sur la classe
        $entityAttrs = $ref->getAttributes(Entity::class);
        if (empty($entityAttrs)) {
            throw new \RuntimeException(sprintf('La classe "%s" n\'a pas l\'attribut #[Entity].', $className));
        }

        $entity = $entityAttrs[0]->newInstance();
        $meta->tableName = $entity->table ?? $this->classToTableName($className);

        // Propriétés
        foreach ($ref->getProperties() as $property) {
            $propName = $property->getName();

            // #[Id]
            $idAttrs = $property->getAttributes(Id::class);
            if (!empty($idAttrs)) {
                $meta->idProperty = $propName;

                $genAttrs = $property->getAttributes(GeneratedValue::class);
                $meta->idGenerated = !empty($genAttrs);
            }

            // #[Column]
            $colAttrs = $property->getAttributes(Column::class);
            if (!empty($colAttrs)) {
                $col = $colAttrs[0]->newInstance();
                $columnName = $col->name ?? $this->propertyToColumnName($propName);
                $meta->columns[$propName] = [
                    'column' => $columnName,
                    'type' => $col->type,
                    'nullable' => $col->nullable,
                    'length' => $col->length,
                    'unique' => $col->unique,
                ];

                if ($propName === $meta->idProperty) {
                    $meta->idColumn = $columnName;
                }
            }

            // #[ManyToOne]
            $mtoAttrs = $property->getAttributes(ManyToOne::class);
            if (!empty($mtoAttrs)) {
                $mto = $mtoAttrs[0]->newInstance();
                $meta->manyToOne[$propName] = [
                    'targetEntity' => $mto->targetEntity,
                    'joinColumn' => $mto->joinColumn ?? $propName . '_id',
                ];
            }

            // #[OneToMany]
            $otmAttrs = $property->getAttributes(OneToMany::class);
            if (!empty($otmAttrs)) {
                $otm = $otmAttrs[0]->newInstance();
                $meta->oneToMany[$propName] = [
                    'targetEntity' => $otm->targetEntity,
                    'mappedBy' => $otm->mappedBy,
                ];
            }
        }

        $this->cache[$className] = $meta;

        return $meta;
    }

    private function classToTableName(string $className): string
    {
        $short = (new \ReflectionClass($className))->getShortName();

        // CamelCase → snake_case + pluriel simple (ajout de 's')
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

        return $snake . 's';
    }

    private function propertyToColumnName(string $property): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
    }
}
