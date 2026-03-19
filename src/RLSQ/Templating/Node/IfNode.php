<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

use RLSQ\Templating\ExpressionCompiler;

class IfNode implements NodeInterface
{
    /**
     * @param array<array{condition: ?string, body: NodeInterface[]}> $branches
     *        Chaque branche a une condition (null = else) et un body.
     */
    public function __construct(
        public readonly array $branches,
    ) {}

    public function compile(): string
    {
        $code = '';

        foreach ($this->branches as $i => $branch) {
            if ($i === 0) {
                $code .= 'if (' . ExpressionCompiler::compile($branch['condition']) . ") {\n";
            } elseif ($branch['condition'] !== null) {
                $code .= '} elseif (' . ExpressionCompiler::compile($branch['condition']) . ") {\n";
            } else {
                $code .= "} else {\n";
            }

            foreach ($branch['body'] as $node) {
                $code .= $node->compile();
            }
        }

        $code .= "}\n";

        return $code;
    }
}
