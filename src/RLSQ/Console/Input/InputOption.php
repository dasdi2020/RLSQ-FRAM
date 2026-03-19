<?php

declare(strict_types=1);

namespace RLSQ\Console\Input;

class InputOption
{
    public const VALUE_NONE = 1;
    public const VALUE_REQUIRED = 2;
    public const VALUE_OPTIONAL = 4;

    public function __construct(
        private readonly string $name,
        private readonly ?string $shortcut = null,
        private readonly int $mode = self::VALUE_NONE,
        private readonly string $description = '',
        private readonly mixed $default = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortcut(): ?string
    {
        return $this->shortcut;
    }

    public function acceptsValue(): bool
    {
        return ($this->mode & (self::VALUE_REQUIRED | self::VALUE_OPTIONAL)) !== 0;
    }

    public function isValueRequired(): bool
    {
        return ($this->mode & self::VALUE_REQUIRED) !== 0;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDefault(): mixed
    {
        if ($this->mode === self::VALUE_NONE) {
            return false;
        }

        return $this->default;
    }
}
