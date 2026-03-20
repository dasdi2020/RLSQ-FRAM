<?php

declare(strict_types=1);

namespace App\Migration;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class M005_CreateProjectsTable implements MigrationInterface
{
    public function up(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                type VARCHAR(30) NOT NULL DEFAULT "website",
                status VARCHAR(30) DEFAULT "draft",
                dns_address VARCHAR(255) DEFAULT NULL,
                db_driver VARCHAR(20) DEFAULT "mysql",
                db_host VARCHAR(255) DEFAULT "localhost",
                db_port VARCHAR(10) DEFAULT "3306",
                db_name VARCHAR(255) DEFAULT "",
                db_user VARCHAR(255) DEFAULT "",
                db_password VARCHAR(500) DEFAULT "",
                db_path VARCHAR(500) DEFAULT "",
                is_provisioned BOOLEAN DEFAULT 0,
                settings TEXT DEFAULT "{}",
                template_config TEXT DEFAULT "{}",
                login_config TEXT DEFAULT "{}",
                created_by INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            )
        ');
        $c->exec('CREATE INDEX IF NOT EXISTS idx_projects_tenant ON projects(tenant_id)');
    }

    public function down(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS projects');
    }

    public function getVersion(): string { return '005'; }
    public function getDescription(): string { return 'Create projects table'; }
}
