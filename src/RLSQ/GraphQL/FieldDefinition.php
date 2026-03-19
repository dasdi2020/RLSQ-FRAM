<?php

declare(strict_types=1);

namespace RLSQ\GraphQL;

class FieldDefinition
{
    /** @var array<string, string> argName => type */
    public array $args = [];

    /**
     * @param string   $type     Type de retour (String, [Article], etc.)
     * @param callable $resolver Fonction de résolution (context, args) => mixed
     */
    public function __construct(
        public readonly string $type,
        public readonly mixed $resolver,
        public readonly ?string $description = null,
    ) {}

    public function addArg(string $name, string $type): static
    {
        $this->args[$name] = $type;
        return $this;
    }

    public function argsSDL(): string
    {
        if (empty($this->args)) {
            return '';
        }

        $parts = [];
        foreach ($this->args as $name => $type) {
            $parts[] = "{$name}: {$type}";
        }

        return '(' . implode(', ', $parts) . ')';
    }
}
