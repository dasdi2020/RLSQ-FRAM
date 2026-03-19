<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

/**
 * Crée les meta-tables pour le schema builder dynamique dans la DB de chaque tenant.
 */
class TenantMetaSchemaMigration implements MigrationInterface
{
    public function up(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS _meta_tables (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                display_name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                icon VARCHAR(50) DEFAULT "table",
                is_system BOOLEAN DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS _meta_columns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT "string",
                length INTEGER DEFAULT 255,
                is_nullable BOOLEAN DEFAULT 0,
                is_unique BOOLEAN DEFAULT 0,
                is_indexed BOOLEAN DEFAULT 0,
                is_primary BOOLEAN DEFAULT 0,
                is_auto_increment BOOLEAN DEFAULT 0,
                default_value TEXT DEFAULT NULL,
                validation_rules TEXT DEFAULT "{}",
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (table_id) REFERENCES _meta_tables(id) ON DELETE CASCADE,
                UNIQUE(table_id, name)
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS _meta_relations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_table_id INTEGER NOT NULL,
                target_table_id INTEGER NOT NULL,
                type VARCHAR(30) NOT NULL DEFAULT "one_to_many",
                source_column VARCHAR(100) DEFAULT NULL,
                target_column VARCHAR(100) DEFAULT NULL,
                pivot_table VARCHAR(100) DEFAULT NULL,
                on_delete VARCHAR(30) DEFAULT "cascade",
                display_name VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (source_table_id) REFERENCES _meta_tables(id) ON DELETE CASCADE,
                FOREIGN KEY (target_table_id) REFERENCES _meta_tables(id) ON DELETE CASCADE
            )
        ');
    }

    public function down(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS _meta_relations');
        $c->exec('DROP TABLE IF EXISTS _meta_columns');
        $c->exec('DROP TABLE IF EXISTS _meta_tables');
    }

    public function getVersion(): string
    {
        return 'T002';
    }

    public function getDescription(): string
    {
        return 'Create meta-tables for dynamic schema builder';
    }
}
