<?php

declare(strict_types=1);

namespace RLSQ\Config\Definition;

/**
 * Interface pour les classes qui définissent un schéma de configuration.
 */
interface ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder;
}
