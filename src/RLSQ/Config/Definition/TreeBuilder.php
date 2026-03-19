<?php

declare(strict_types=1);

namespace RLSQ\Config\Definition;

/**
 * Construit un arbre de validation pour la configuration.
 *
 * Usage :
 *   $tree = new TreeBuilder('framework');
 *   $root = $tree->getRootNode();
 *   $root->addChild((new ScalarNode('secret'))->isRequired());
 *   $root->addChild((new ScalarNode('debug'))->defaultValue(false)->type('bool'));
 *   $root->addChild(
 *       (new ArrayNode('database'))
 *           ->addChild((new ScalarNode('host'))->defaultValue('localhost'))
 *           ->addChild((new ScalarNode('port'))->defaultValue(3306))
 *   );
 *
 *   $processed = $tree->process($rawConfig);
 */
class TreeBuilder
{
    private ArrayNode $root;

    public function __construct(string $rootName)
    {
        $this->root = new ArrayNode($rootName);
    }

    public function getRootNode(): ArrayNode
    {
        return $this->root;
    }

    /**
     * Valide et normalise un tableau de config selon l'arbre.
     */
    public function process(array $config): array
    {
        $result = $this->root->process($config);

        return is_array($result) ? $result : [];
    }
}
