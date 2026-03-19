<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection\Compiler;

use RLSQ\DependencyInjection\ContainerBuilder;

/**
 * Interface pour les passes de compilation.
 * Une passe peut modifier les définitions avant la compilation finale.
 */
interface CompilerPassInterface
{
    public function process(ContainerBuilder $container): void;
}
