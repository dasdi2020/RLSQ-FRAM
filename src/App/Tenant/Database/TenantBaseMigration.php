<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

/**
 * Migration de base exécutée dans chaque nouvelle DB de tenant.
 * Crée les tables fondamentales communes à tous les tenants.
 */
class TenantBaseMigration implements MigrationInterface
{
    public function up(Connection $connection): void
    {
        // Membres du tenant
        $connection->exec('
            CREATE TABLE IF NOT EXISTS members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) DEFAULT "",
                last_name VARCHAR(100) DEFAULT "",
                phone VARCHAR(50) DEFAULT NULL,
                roles TEXT DEFAULT \'["ROLE_MEMBER"]\',
                status VARCHAR(20) DEFAULT "active",
                club_id INTEGER DEFAULT NULL,
                avatar_url VARCHAR(500) DEFAULT NULL,
                settings TEXT DEFAULT "{}",
                external_user_id INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        $connection->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_members_email ON members(email)');

        // Clubs (sous-organisations)
        $connection->exec('
            CREATE TABLE IF NOT EXISTS clubs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                description TEXT DEFAULT NULL,
                address VARCHAR(500) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                settings TEXT DEFAULT "{}",
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Association membres-clubs
        $connection->exec('
            CREATE TABLE IF NOT EXISTS member_clubs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                member_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                role VARCHAR(50) DEFAULT "member",
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                UNIQUE(member_id, club_id)
            )
        ');

        // Table de config du tenant (key-value)
        $connection->exec('
            CREATE TABLE IF NOT EXISTS _tenant_config (
                key VARCHAR(100) PRIMARY KEY,
                value TEXT
            )
        ');

        // État des plugins installés
        $connection->exec('
            CREATE TABLE IF NOT EXISTS _plugin_state (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                plugin_slug VARCHAR(100) NOT NULL UNIQUE,
                version VARCHAR(20) DEFAULT "1.0.0",
                is_active BOOLEAN DEFAULT 1,
                settings TEXT DEFAULT "{}",
                installed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Audit log
        $connection->exec('
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(100) DEFAULT NULL,
                entity_id INTEGER DEFAULT NULL,
                changes TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    public function down(Connection $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS audit_logs');
        $connection->exec('DROP TABLE IF EXISTS _plugin_state');
        $connection->exec('DROP TABLE IF EXISTS _tenant_config');
        $connection->exec('DROP TABLE IF EXISTS member_clubs');
        $connection->exec('DROP TABLE IF EXISTS clubs');
        $connection->exec('DROP TABLE IF EXISTS members');
    }

    public function getVersion(): string
    {
        return 'T001';
    }

    public function getDescription(): string
    {
        return 'Base tenant schema: members, clubs, config, plugins, audit';
    }
}
