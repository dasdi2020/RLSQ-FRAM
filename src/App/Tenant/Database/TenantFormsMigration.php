<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class TenantFormsMigration implements MigrationInterface
{
    public function up(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS form_definitions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                description TEXT DEFAULT NULL,
                table_id INTEGER DEFAULT NULL,
                layout TEXT DEFAULT "{}",
                settings TEXT DEFAULT "{}",
                is_published BOOLEAN DEFAULT 0,
                version INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS form_fields (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                form_id INTEGER NOT NULL,
                column_id INTEGER DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT "text",
                label VARCHAR(255) NOT NULL,
                placeholder VARCHAR(255) DEFAULT NULL,
                help_text VARCHAR(500) DEFAULT NULL,
                default_value TEXT DEFAULT NULL,
                is_required BOOLEAN DEFAULT 0,
                is_visible BOOLEAN DEFAULT 1,
                is_readonly BOOLEAN DEFAULT 0,
                visibility_rules TEXT DEFAULT "{}",
                validation TEXT DEFAULT "{}",
                options TEXT DEFAULT "{}",
                width INTEGER DEFAULT 12,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (form_id) REFERENCES form_definitions(id) ON DELETE CASCADE
            )
        ');
        $c->exec('CREATE INDEX IF NOT EXISTS idx_form_fields_form ON form_fields(form_id)');

        $c->exec('
            CREATE TABLE IF NOT EXISTS form_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                form_id INTEGER NOT NULL,
                submitted_by INTEGER DEFAULT NULL,
                data TEXT DEFAULT "{}",
                status VARCHAR(30) DEFAULT "submitted",
                ip_address VARCHAR(45) DEFAULT NULL,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (form_id) REFERENCES form_definitions(id) ON DELETE CASCADE
            )
        ');
        $c->exec('CREATE INDEX IF NOT EXISTS idx_submissions_form ON form_submissions(form_id)');
    }

    public function down(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS form_submissions');
        $c->exec('DROP TABLE IF EXISTS form_fields');
        $c->exec('DROP TABLE IF EXISTS form_definitions');
    }

    public function getVersion(): string
    {
        return 'T004';
    }

    public function getDescription(): string
    {
        return 'Create form builder tables';
    }
}
