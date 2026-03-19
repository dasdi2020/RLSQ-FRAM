<?php

declare(strict_types=1);

namespace RLSQ\Templating;

use RLSQ\Templating\Node\NodeInterface;

/**
 * Compile un AST (tableau de nœuds) en code PHP exécutable.
 */
class Compiler
{
    /**
     * @param NodeInterface[] $nodes
     */
    public function compile(array $nodes): string
    {
        $code = '';

        foreach ($nodes as $node) {
            $code .= $node->compile();
        }

        return $code;
    }
}
