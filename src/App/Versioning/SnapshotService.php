<?php

declare(strict_types=1);

namespace App\Versioning;

use RLSQ\Database\Connection;

/**
 * Capture et restaure l'état complet d'un tenant :
 * meta-tables, formulaires, pages, plugins, dashboards, config.
 */
class SnapshotService
{
    public function __construct(
        private readonly Connection $platformConnection,
    ) {}

    /**
     * Capture un snapshot complet de la DB d'un tenant.
     */
    public function capture(Connection $tenantConnection, int $tenantId, string $versionTag, ?string $description = null, ?int $createdBy = null): array
    {
        // Vérifier l'unicité du tag
        $existing = $this->platformConnection->fetchOne(
            'SELECT id FROM app_versions WHERE tenant_id = :tid AND version_tag = :vt',
            ['tid' => $tenantId, 'vt' => $versionTag],
        );
        if ($existing) {
            throw new \RuntimeException(sprintf('La version "%s" existe déjà.', $versionTag));
        }

        $snapshot = $this->buildSnapshot($tenantConnection);

        $this->platformConnection->execute(
            'INSERT INTO app_versions (tenant_id, version_tag, description, snapshot_data, status, created_by) VALUES (:tid, :vt, :d, :sd, :s, :cb)',
            [
                'tid' => $tenantId, 'vt' => $versionTag,
                'd' => $description, 'sd' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                's' => 'published', 'cb' => $createdBy,
            ],
        );

        $id = (int) $this->platformConnection->lastInsertId();

        return $this->getVersion($id);
    }

    /**
     * Restaure un snapshot dans la DB d'un tenant.
     */
    public function restore(Connection $tenantConnection, int $versionId): array
    {
        $version = $this->getVersion($versionId);
        if (!$version) {
            throw new \RuntimeException('Version introuvable.');
        }

        $snapshot = json_decode($version['snapshot_data'], true);
        if (!$snapshot) {
            throw new \RuntimeException('Snapshot invalide.');
        }

        $this->restoreSnapshot($tenantConnection, $snapshot);

        return ['status' => 'restored', 'version' => $version['version_tag']];
    }

    /**
     * Compare deux snapshots et retourne les différences.
     */
    public function diff(int $versionId1, int $versionId2): array
    {
        $v1 = $this->getVersion($versionId1);
        $v2 = $this->getVersion($versionId2);

        if (!$v1 || !$v2) {
            throw new \RuntimeException('Version introuvable.');
        }

        $s1 = json_decode($v1['snapshot_data'], true);
        $s2 = json_decode($v2['snapshot_data'], true);

        $changes = [];

        foreach (['meta_tables', 'forms', 'pages', 'plugins', 'dashboards'] as $section) {
            $count1 = count($s1[$section] ?? []);
            $count2 = count($s2[$section] ?? []);

            if ($count1 !== $count2) {
                $changes[] = [
                    'section' => $section,
                    'type' => $count2 > $count1 ? 'added' : 'removed',
                    'detail' => sprintf('%s : %d → %d', $section, $count1, $count2),
                ];
            }
        }

        return [
            'from' => $v1['version_tag'],
            'to' => $v2['version_tag'],
            'changes' => $changes,
            'from_date' => $v1['created_at'],
            'to_date' => $v2['created_at'],
        ];
    }

    public function getVersion(int $id): ?array
    {
        $v = $this->platformConnection->fetchOne('SELECT * FROM app_versions WHERE id = :id', ['id' => $id]);
        return $v ?: null;
    }

    /** @return array[] */
    public function getVersionsForTenant(int $tenantId): array
    {
        $versions = $this->platformConnection->fetchAll(
            'SELECT id, tenant_id, version_tag, description, status, created_by, created_at FROM app_versions WHERE tenant_id = :tid ORDER BY created_at DESC',
            ['tid' => $tenantId],
        );

        foreach ($versions as &$v) {
            // Ajouter un résumé sans le snapshot complet
            $snapshot = json_decode(
                $this->platformConnection->fetchColumn('SELECT snapshot_data FROM app_versions WHERE id = :id', ['id' => $v['id']]) ?: '{}',
                true,
            );
            $v['summary'] = [
                'tables' => count($snapshot['meta_tables'] ?? []),
                'forms' => count($snapshot['forms'] ?? []),
                'pages' => count($snapshot['pages'] ?? []),
                'plugins' => count($snapshot['plugins'] ?? []),
                'dashboards' => count($snapshot['dashboards'] ?? []),
            ];
        }

        return $versions;
    }

    public function deleteVersion(int $id): void
    {
        $this->platformConnection->execute('DELETE FROM app_versions WHERE id = :id', ['id' => $id]);
    }

    // ==================== PRIVATE ====================

    private function buildSnapshot(Connection $tc): array
    {
        return [
            'captured_at' => date('Y-m-d H:i:s'),
            'meta_tables' => $this->exportTable($tc, '_meta_tables'),
            'meta_columns' => $this->exportTable($tc, '_meta_columns'),
            'meta_relations' => $this->exportTable($tc, '_meta_relations'),
            'forms' => $this->exportTable($tc, 'form_definitions'),
            'form_fields' => $this->exportTable($tc, 'form_fields'),
            'pages' => $this->exportTable($tc, 'pages'),
            'page_components' => $this->exportTable($tc, 'page_components'),
            'dashboards' => $this->exportTable($tc, 'dashboards'),
            'dashboard_widgets' => $this->exportTable($tc, 'dashboard_widgets'),
            'plugins' => $this->exportTable($tc, '_plugin_state'),
            'tenant_config' => $this->exportTable($tc, '_tenant_config'),
        ];
    }

    private function exportTable(Connection $c, string $table): array
    {
        try {
            return $c->fetchAll("SELECT * FROM \"{$table}\"");
        } catch (\Throwable) {
            return [];
        }
    }

    private function restoreSnapshot(Connection $tc, array $snapshot): void
    {
        $tableMap = [
            '_meta_tables' => 'meta_tables',
            '_meta_columns' => 'meta_columns',
            '_meta_relations' => 'meta_relations',
            'form_definitions' => 'forms',
            'form_fields' => 'form_fields',
            'pages' => 'pages',
            'page_components' => 'page_components',
            'dashboards' => 'dashboards',
            'dashboard_widgets' => 'dashboard_widgets',
            '_plugin_state' => 'plugins',
            '_tenant_config' => 'tenant_config',
        ];

        // Ordre de suppression (respecter les FK)
        $deleteOrder = [
            'dashboard_widgets', 'dashboards', 'page_components', 'pages',
            'form_fields', 'form_definitions', '_meta_relations', '_meta_columns',
            '_meta_tables', '_plugin_state', '_tenant_config',
        ];

        foreach ($deleteOrder as $table) {
            try { $tc->exec("DELETE FROM \"{$table}\""); } catch (\Throwable) {}
        }

        // Restaurer dans l'ordre inverse
        $insertOrder = array_reverse($deleteOrder);

        foreach ($insertOrder as $table) {
            $snapshotKey = $tableMap[$table] ?? $table;
            $rows = $snapshot[$snapshotKey] ?? [];

            foreach ($rows as $row) {
                if (empty($row)) {
                    continue;
                }

                $cols = array_keys($row);
                $placeholders = array_map(fn ($c) => ':' . $c, $cols);

                try {
                    $tc->execute(
                        sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $table, implode(', ', $cols), implode(', ', $placeholders)),
                        $row,
                    );
                } catch (\Throwable) {
                    // Ignorer les erreurs d'insertion silencieusement (ex: contraintes)
                }
            }
        }
    }
}
