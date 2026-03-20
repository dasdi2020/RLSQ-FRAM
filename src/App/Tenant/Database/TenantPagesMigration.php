<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class TenantPagesMigration implements MigrationInterface
{
    public function up(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                route_path VARCHAR(255),
                is_published BOOLEAN DEFAULT 0,
                version INTEGER DEFAULT 1,
                parent_id INTEGER DEFAULT NULL,
                meta_title VARCHAR(255) DEFAULT NULL,
                meta_description TEXT DEFAULT NULL,
                access_roles TEXT DEFAULT "[]",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS page_components (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_id INTEGER NOT NULL,
                type VARCHAR(50) NOT NULL,
                props TEXT DEFAULT "{}",
                styles TEXT DEFAULT "{}",
                content TEXT DEFAULT "",
                children_ids TEXT DEFAULT "[]",
                position_x INTEGER DEFAULT 0,
                position_y INTEGER DEFAULT 0,
                width INTEGER DEFAULT 12,
                height INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0,
                parent_component_id INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
            )
        ');
        $c->exec('CREATE INDEX IF NOT EXISTS idx_page_comp ON page_components(page_id)');
    }

    public function down(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS page_components');
        $c->exec('DROP TABLE IF EXISTS pages');
    }

    public function getVersion(): string { return 'T005'; }
    public function getDescription(): string { return 'Create pages and page_components tables'; }
}
