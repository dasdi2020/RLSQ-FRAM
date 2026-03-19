<?php

declare(strict_types=1);

namespace RLSQ\Console\Input;

class InputArgument
{
    public const REQUIRED = 1;
    public const OPTIONAL = 2;

    public function __construct(
        private readonly string $name,
        private readonly int $mode = self::OPTIONAL,
        private readonly string $description = '',
        private readonly mixed $default = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isRequired(): bool
    {
        return ($this->mode & self::REQUIRED) !== 0;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }
}
