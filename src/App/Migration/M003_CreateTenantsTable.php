<?php

declare(strict_types=1);

namespace App\Migration;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class M003_CreateTenantsTable implements MigrationInterface
{
    public function up(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE IF NOT EXISTS tenants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) DEFAULT "organization",
                domain VARCHAR(255) DEFAULT NULL,
                db_driver VARCHAR(20) DEFAULT "sqlite",
                db_host VARCHAR(255) DEFAULT "localhost",
                db_port VARCHAR(10) DEFAULT "3306",
                db_name VARCHAR(255) DEFAULT "",
                db_user VARCHAR(255) DEFAULT "",
                db_password VARCHAR(500) DEFAULT "",
                db_path VARCHAR(500) DEFAULT "",
                is_active BOOLEAN DEFAULT 1,
                is_provisioned BOOLEAN DEFAULT 0,
                settings TEXT DEFAULT "{}",
                logo_url VARCHAR(500) DEFAULT NULL,
                primary_color VARCHAR(20) DEFAULT "#ff3e00",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $connection->exec('
            CREATE TABLE IF NOT EXISTS tenant_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                roles TEXT DEFAULT "[]",
                is_primary BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(tenant_id, user_id)
            )
        ');

        $connection->exec('CREATE INDEX IF NOT EXISTS idx_tenant_users_tenant ON tenant_users(tenant_id)');
        $connection->exec('CREATE INDEX IF NOT EXISTS idx_tenant_users_user ON tenant_users(user_id)');
    }

    public function down(Connection $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS tenant_users');
        $connection->exec('DROP TABLE IF EXISTS tenants');
    }

    public function getVersion(): string
    {
        return '003';
    }

    public function getDescription(): string
    {
        return 'Create tenants and tenant_users tables';
    }
}
