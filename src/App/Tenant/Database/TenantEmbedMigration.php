<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class TenantEmbedMigration implements MigrationInterface
{
    public function up(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS embed_configs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                module_slug VARCHAR(100) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                allowed_domains TEXT DEFAULT "[]",
                settings TEXT DEFAULT "{}",
                theme TEXT DEFAULT "{}",
                is_active BOOLEAN DEFAULT 1,
                views_count INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    public function down(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS embed_configs');
    }

    public function getVersion(): string { return 'T006'; }
    public function getDescription(): string { return 'Create embed_configs table'; }
}
