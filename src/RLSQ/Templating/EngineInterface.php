<?php

declare(strict_types=1);

namespace RLSQ\Templating;

interface EngineInterface
{
    public function render(string $name, array $parameters = []): string;

    public function exists(string $name): bool;
}
