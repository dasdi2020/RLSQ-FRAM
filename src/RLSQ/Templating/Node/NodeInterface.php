<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

interface NodeInterface
{
    /**
     * Compile le nœud en code PHP exécutable.
     */
    public function compile(): string;
}
