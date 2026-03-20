<?php

declare(strict_types=1);

namespace App\Migration;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class M004_CreateVersionsTables implements MigrationInterface
{
    public function up(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS app_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                version_tag VARCHAR(50) NOT NULL,
                description TEXT DEFAULT NULL,
                snapshot_data TEXT NOT NULL,
                status VARCHAR(30) DEFAULT "draft",
                created_by INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            )
        ');
        $c->exec('CREATE INDEX IF NOT EXISTS idx_versions_tenant ON app_versions(tenant_id)');
        $c->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_versions_tag ON app_versions(tenant_id, version_tag)');

        $c->exec('
            CREATE TABLE IF NOT EXISTS app_deployments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                version_id INTEGER NOT NULL,
                target VARCHAR(30) DEFAULT "production",
                status VARCHAR(30) DEFAULT "pending",
                deploy_config TEXT DEFAULT "{}",
                log TEXT DEFAULT "",
                deployed_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (version_id) REFERENCES app_versions(id) ON DELETE CASCADE
            )
        ');
    }

    public function down(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS app_deployments');
        $c->exec('DROP TABLE IF EXISTS app_versions');
    }

    public function getVersion(): string { return '004'; }
    public function getDescription(): string { return 'Create app_versions and app_deployments tables'; }
}
