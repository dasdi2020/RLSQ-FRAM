<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

class ExtendsNode implements NodeInterface
{
    public function __construct(
        public readonly string $parent,
    ) {}

    public function compile(): string
    {
        // L'extends est géré au niveau du Engine, pas au compile time.
        // Ce nœud sert de marqueur.
        return '';
    }
}
