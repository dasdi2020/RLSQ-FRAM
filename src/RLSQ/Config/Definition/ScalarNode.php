<?php

declare(strict_types=1);

namespace RLSQ\Config\Definition;

use RLSQ\Config\Exception\ParseException;

class ScalarNode implements NodeInterface
{
    private mixed $defaultValue = null;
    private bool $required = false;
    private bool $hasDefault = false;
    /** @var string[]|null */
    private ?array $allowedValues = null;
    private ?string $type = null;

    public function __construct(
        private readonly string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function defaultValue(mixed $value): static
    {
        $this->defaultValue = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function isRequired(): static
    {
        $this->required = true;
        return $this;
    }

    public function allowedValues(array $values): static
    {
        $this->allowedValues = $values;
        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function process(mixed $value): mixed
    {
        if ($value === null) {
            if ($this->required) {
                throw new ParseException(sprintf('La clé "%s" est requise.', $this->name));
            }
            if ($this->hasDefault) {
                return $this->defaultValue;
            }
            return null;
        }

        if ($this->type !== null) {
            $actualType = get_debug_type($value);
            if ($actualType !== $this->type && !($this->type === 'number' && is_numeric($value))) {
                throw new ParseException(sprintf('La clé "%s" attend un "%s", "%s" donné.', $this->name, $this->type, $actualType));
            }
        }

        if ($this->allowedValues !== null && !in_array($value, $this->allowedValues, true)) {
            throw new ParseException(sprintf(
                'La valeur "%s" n\'est pas autorisée pour "%s". Valeurs acceptées : %s.',
                (string) $value,
                $this->name,
                implode(', ', array_map(fn ($v) => (string) $v, $this->allowedValues)),
            ));
        }

        return $value;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function getHasDefault(): bool
    {
        return $this->hasDefault;
    }
}
