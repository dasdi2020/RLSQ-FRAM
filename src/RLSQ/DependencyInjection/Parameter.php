<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection;

/**
 * Représente une référence vers un paramètre du Container (%param_name%).
 */
class Parameter
{
    public function __construct(
        private readonly string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return '%' . $this->name . '%';
    }
}
