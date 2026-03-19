<?php

declare(strict_types=1);

namespace RLSQ\Plugin;

/**
 * Registre de tous les plugins disponibles dans la plateforme.
 */
class PluginRegistry
{
    /** @var array<string, PluginInterface> slug => plugin */
    private array $plugins = [];

    public function register(PluginInterface $plugin): void
    {
        $this->plugins[$plugin->getSlug()] = $plugin;
    }

    public function get(string $slug): ?PluginInterface
    {
        return $this->plugins[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        return isset($this->plugins[$slug]);
    }

    /**
     * @return PluginInterface[]
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * Retourne les infos de tous les plugins pour l'API.
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->plugins as $plugin) {
            $result[] = $plugin->toArray();
        }

        return $result;
    }
}
