<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

use RLSQ\Templating\ExpressionCompiler;

/**
 * {{ expression|raw }} — affichage sans échappement.
 */
class RawNode implements NodeInterface
{
    public function __construct(
        public readonly string $expression,
    ) {}

    public function compile(): string
    {
        return 'echo ' . ExpressionCompiler::compile($this->expression, autoEscape: false) . ";\n";
    }
}
