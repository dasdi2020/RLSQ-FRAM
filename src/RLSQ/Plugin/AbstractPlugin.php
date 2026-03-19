<?php

declare(strict_types=1);

namespace RLSQ\Plugin;

use RLSQ\Database\Connection;
use RLSQ\Routing\RouteCollection;

/**
 * Classe de base pour les plugins. Fournit des implémentations par défaut.
 */
abstract class AbstractPlugin implements PluginInterface
{
    public function getAuthor(): string
    {
        return 'RLSQ-FRAM';
    }

    public function getIcon(): string
    {
        return 'puzzle';
    }

    public function install(Connection $tenantConnection): void
    {
        // Override dans les sous-classes
    }

    public function uninstall(Connection $tenantConnection): void
    {
        // Override dans les sous-classes
    }

    public function getRoutes(): RouteCollection
    {
        return new RouteCollection();
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return ['fields' => []];
    }

    /**
     * Sérialise les infos du plugin pour l'API.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'slug' => $this->getSlug(),
            'version' => $this->getVersion(),
            'description' => $this->getDescription(),
            'author' => $this->getAuthor(),
            'icon' => $this->getIcon(),
            'menu_items' => $this->getMenuItems(),
            'settings_schema' => $this->getSettingsSchema(),
        ];
    }
}
