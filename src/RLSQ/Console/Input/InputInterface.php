<?php

declare(strict_types=1);

namespace RLSQ\Console\Input;

interface InputInterface
{
    public function getArgument(string $name): mixed;

    public function hasArgument(string $name): bool;

    public function getOption(string $name): mixed;

    public function hasOption(string $name): bool;

    /**
     * Parse l'input selon une définition.
     */
    public function bind(InputDefinition $definition): void;
}
