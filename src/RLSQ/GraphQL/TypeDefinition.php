<?php

declare(strict_types=1);

namespace RLSQ\GraphQL;

class TypeDefinition
{
    /** @var array<string, string> fieldName => type (String, Int, Boolean, [Type], Type!) */
    public array $fields = [];

    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
    ) {}

    public function addField(string $name, string $type): static
    {
        $this->fields[$name] = $type;
        return $this;
    }

    public function toSDL(): string
    {
        $sdl = '';
        if ($this->description !== null) {
            $sdl .= '"""' . $this->description . '"""' . "\n";
        }

        $sdl .= "type {$this->name} {\n";
        foreach ($this->fields as $field => $type) {
            $sdl .= "  {$field}: {$type}\n";
        }
        $sdl .= '}';

        return $sdl;
    }
}
