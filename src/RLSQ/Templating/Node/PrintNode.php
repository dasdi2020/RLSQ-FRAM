<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

use RLSQ\Templating\ExpressionCompiler;

/**
 * {{ expression }} ou {{ expression|filtre }}
 */
class PrintNode implements NodeInterface
{
    public function __construct(
        public readonly string $expression,
    ) {}

    public function compile(): string
    {
        return 'echo ' . ExpressionCompiler::compile($this->expression, autoEscape: true) . ";\n";
    }
}
