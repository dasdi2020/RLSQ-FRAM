<?php

declare(strict_types=1);

namespace RLSQ\Config\Definition;

/**
 * Valide et normalise un tableau de config selon un ConfigurationInterface.
 * Fusionne plusieurs tableaux de config (ex: default + user config).
 */
class Processor
{
    /**
     * @param array[] $configs Tableaux de config à fusionner et valider
     */
    public function processConfiguration(ConfigurationInterface $configuration, array $configs): array
    {
        $merged = $this->mergeConfigs($configs);

        $tree = $configuration->getConfigTreeBuilder();

        return $tree->process($merged);
    }

    /**
     * Fusionne plusieurs tableaux de config (le dernier gagne pour les scalaires).
     */
    private function mergeConfigs(array $configs): array
    {
        $result = [];

        foreach ($configs as $config) {
            $result = $this->mergeRecursive($result, $config);
        }

        return $result;
    }

    private function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
