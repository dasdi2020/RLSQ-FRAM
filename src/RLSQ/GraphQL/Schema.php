<?php

declare(strict_types=1);

namespace RLSQ\GraphQL;

/**
 * Définition du schéma GraphQL avec types, queries et mutations.
 */
class Schema
{
    /** @var array<string, TypeDefinition> */
    private array $types = [];

    /** @var array<string, FieldDefinition> */
    private array $queries = [];

    /** @var array<string, FieldDefinition> */
    private array $mutations = [];

    public function addType(TypeDefinition $type): static
    {
        $this->types[$type->name] = $type;
        return $this;
    }

    public function addQuery(string $name, FieldDefinition $field): static
    {
        $this->queries[$name] = $field;
        return $this;
    }

    public function addMutation(string $name, FieldDefinition $field): static
    {
        $this->mutations[$name] = $field;
        return $this;
    }

    public function getType(string $name): ?TypeDefinition
    {
        return $this->types[$name] ?? null;
    }

    /** @return array<string, TypeDefinition> */
    public function getTypes(): array
    {
        return $this->types;
    }

    /** @return array<string, FieldDefinition> */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /** @return array<string, FieldDefinition> */
    public function getMutations(): array
    {
        return $this->mutations;
    }

    /**
     * Génère le schéma SDL (Schema Definition Language).
     */
    public function toSDL(): string
    {
        $sdl = '';

        foreach ($this->types as $type) {
            $sdl .= $type->toSDL() . "\n\n";
        }

        if (!empty($this->queries)) {
            $sdl .= "type Query {\n";
            foreach ($this->queries as $name => $field) {
                $sdl .= "  {$name}{$field->argsSDL()}: {$field->type}\n";
            }
            $sdl .= "}\n\n";
        }

        if (!empty($this->mutations)) {
            $sdl .= "type Mutation {\n";
            foreach ($this->mutations as $name => $field) {
                $sdl .= "  {$name}{$field->argsSDL()}: {$field->type}\n";
            }
            $sdl .= "}\n";
        }

        return $sdl;
    }
}
