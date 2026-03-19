<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection;

/**
 * Représente une référence vers un autre service dans le Container.
 */
class Reference
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
