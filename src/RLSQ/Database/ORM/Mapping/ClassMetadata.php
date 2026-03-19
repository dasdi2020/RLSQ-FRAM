<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM\Mapping;

/**
 * Métadonnées de mapping pour une entité.
 */
class ClassMetadata
{
    public string $className;
    public string $tableName;
    public ?string $idProperty = null;
    public ?string $idColumn = null;
    public bool $idGenerated = false;

    /**
     * @var array<string, array{column: string, type: string, nullable: bool}>
     * propertyName => {column, type, nullable}
     */
    public array $columns = [];

    /**
     * @var array<string, array{targetEntity: string, joinColumn: string}>
     */
    public array $manyToOne = [];

    /**
     * @var array<string, array{targetEntity: string, mappedBy: string}>
     */
    public array $oneToMany = [];

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function getColumnForProperty(string $property): ?string
    {
        return $this->columns[$property]['column'] ?? null;
    }

    public function getPropertyForColumn(string $column): ?string
    {
        foreach ($this->columns as $prop => $info) {
            if ($info['column'] === $column) {
                return $prop;
            }
        }

        return null;
    }

    /**
     * @return string[] Noms des colonnes (sans l'id si auto-généré)
     */
    public function getInsertColumns(): array
    {
        $cols = [];
        foreach ($this->columns as $prop => $info) {
            if ($prop === $this->idProperty && $this->idGenerated) {
                continue;
            }
            $cols[] = $info['column'];
        }

        return $cols;
    }
}
