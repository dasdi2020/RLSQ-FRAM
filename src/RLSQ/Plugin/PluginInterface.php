<?php

declare(strict_types=1);

namespace RLSQ\Plugin;

use RLSQ\Database\Connection;
use RLSQ\Routing\RouteCollection;

interface PluginInterface
{
    public function getName(): string;

    public function getSlug(): string;

    public function getVersion(): string;

    public function getDescription(): string;

    public function getAuthor(): string;

    public function getIcon(): string;

    /**
     * Installe le plugin (migrations, tables, données initiales).
     */
    public function install(Connection $tenantConnection): void;

    /**
     * Désinstalle le plugin (supprime les tables).
     */
    public function uninstall(Connection $tenantConnection): void;

    /**
     * Retourne les routes fournies par le plugin.
     */
    public function getRoutes(): RouteCollection;

    /**
     * Retourne les items de menu pour le dashboard.
     * Format : [['label' => 'Formations', 'icon' => 'book', 'path' => '/formations'], ...]
     */
    public function getMenuItems(): array;

    /**
     * Schéma JSON des settings configurables par le tenant.
     * Format : ['fields' => [['name' => 'max_capacity', 'type' => 'integer', 'label' => '...'], ...]]
     */
    public function getSettingsSchema(): array;
}
