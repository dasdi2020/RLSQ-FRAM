<?php

declare(strict_types=1);

namespace RLSQ\Plugin;

use RLSQ\Database\Connection;

/**
 * Gère l'installation, l'activation et la configuration des plugins par tenant.
 * Utilise la table _plugin_state dans la DB du tenant.
 */
class PluginManager
{
    public function __construct(
        private readonly PluginRegistry $registry,
    ) {}

    /**
     * Installe un plugin pour un tenant.
     */
    public function install(string $slug, Connection $tenantConnection, array $settings = []): array
    {
        $plugin = $this->registry->get($slug);
        if ($plugin === null) {
            throw new \RuntimeException(sprintf('Plugin "%s" introuvable dans le registre.', $slug));
        }

        if ($this->isInstalled($slug, $tenantConnection)) {
            throw new \RuntimeException(sprintf('Plugin "%s" déjà installé.', $slug));
        }

        // Exécuter les migrations du plugin
        $plugin->install($tenantConnection);

        // Enregistrer dans _plugin_state
        $tenantConnection->execute(
            'INSERT INTO _plugin_state (plugin_slug, version, is_active, settings) VALUES (:s, :v, 1, :st)',
            ['s' => $slug, 'v' => $plugin->getVersion(), 'st' => json_encode($settings)],
        );

        return $this->getState($slug, $tenantConnection);
    }

    /**
     * Désinstalle un plugin.
     */
    public function uninstall(string $slug, Connection $tenantConnection): void
    {
        $plugin = $this->registry->get($slug);

        if ($plugin !== null) {
            $plugin->uninstall($tenantConnection);
        }

        $tenantConnection->execute('DELETE FROM _plugin_state WHERE plugin_slug = :s', ['s' => $slug]);
    }

    /**
     * Active un plugin installé.
     */
    public function activate(string $slug, Connection $tenantConnection): void
    {
        $tenantConnection->execute(
            'UPDATE _plugin_state SET is_active = 1 WHERE plugin_slug = :s',
            ['s' => $slug],
        );
    }

    /**
     * Désactive un plugin sans le désinstaller.
     */
    public function deactivate(string $slug, Connection $tenantConnection): void
    {
        $tenantConnection->execute(
            'UPDATE _plugin_state SET is_active = 0 WHERE plugin_slug = :s',
            ['s' => $slug],
        );
    }

    /**
     * Met à jour les settings d'un plugin.
     */
    public function updateSettings(string $slug, Connection $tenantConnection, array $settings): void
    {
        $tenantConnection->execute(
            'UPDATE _plugin_state SET settings = :st WHERE plugin_slug = :s',
            ['s' => $slug, 'st' => json_encode($settings)],
        );
    }

    /**
     * Vérifie si un plugin est installé pour ce tenant.
     */
    public function isInstalled(string $slug, Connection $tenantConnection): bool
    {
        $row = $tenantConnection->fetchOne(
            'SELECT id FROM _plugin_state WHERE plugin_slug = :s',
            ['s' => $slug],
        );

        return $row !== false;
    }

    /**
     * Vérifie si un plugin est actif pour ce tenant.
     */
    public function isActive(string $slug, Connection $tenantConnection): bool
    {
        $row = $tenantConnection->fetchOne(
            'SELECT is_active FROM _plugin_state WHERE plugin_slug = :s',
            ['s' => $slug],
        );

        return $row !== false && (int) $row['is_active'] === 1;
    }

    /**
     * Retourne l'état d'un plugin pour un tenant.
     */
    public function getState(string $slug, Connection $tenantConnection): ?array
    {
        $row = $tenantConnection->fetchOne(
            'SELECT * FROM _plugin_state WHERE plugin_slug = :s',
            ['s' => $slug],
        );

        return $row ?: null;
    }

    /**
     * Retourne tous les plugins installés pour un tenant.
     */
    public function getInstalledPlugins(Connection $tenantConnection): array
    {
        return $tenantConnection->fetchAll('SELECT * FROM _plugin_state ORDER BY plugin_slug');
    }

    /**
     * Retourne la liste complète des plugins avec leur statut pour un tenant.
     */
    public function getPluginsWithStatus(Connection $tenantConnection): array
    {
        $installed = $this->getInstalledPlugins($tenantConnection);
        $installedMap = [];

        foreach ($installed as $state) {
            $installedMap[$state['plugin_slug']] = $state;
        }

        $result = [];

        foreach ($this->registry->all() as $plugin) {
            $slug = $plugin->getSlug();
            $state = $installedMap[$slug] ?? null;

            $result[] = array_merge($plugin->toArray(), [
                'is_installed' => $state !== null,
                'is_active' => $state !== null && (int) $state['is_active'] === 1,
                'installed_version' => $state['version'] ?? null,
                'settings' => $state ? json_decode($state['settings'] ?? '{}', true) : [],
                'installed_at' => $state['installed_at'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * Retourne les plugins actifs pour un tenant (avec leurs instances).
     *
     * @return PluginInterface[]
     */
    public function getActivePlugins(Connection $tenantConnection): array
    {
        $active = $tenantConnection->fetchAll(
            'SELECT plugin_slug FROM _plugin_state WHERE is_active = 1',
        );

        $plugins = [];

        foreach ($active as $row) {
            $plugin = $this->registry->get($row['plugin_slug']);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }
}
