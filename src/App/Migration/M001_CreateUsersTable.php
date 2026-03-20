<?php

declare(strict_types=1);

namespace App\Migration;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class M001_CreateUsersTable implements MigrationInterface
{
    public function up(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL DEFAULT "",
                last_name VARCHAR(100) NOT NULL DEFAULT "",
                roles TEXT DEFAULT "[]",
                is_active BOOLEAN DEFAULT 1,
                two_factor_enabled BOOLEAN DEFAULT 1,
                mfa_method VARCHAR(20) DEFAULT "email",
                totp_secret VARCHAR(255) DEFAULT NULL,
                totp_secret_pending VARCHAR(255) DEFAULT NULL,
                avatar_url VARCHAR(500) DEFAULT NULL,
                last_login_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $connection->exec('
            CREATE TABLE IF NOT EXISTS two_factor_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                email VARCHAR(255) NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ');

        $connection->exec('CREATE INDEX IF NOT EXISTS idx_2fa_user ON two_factor_codes(user_id, used)');
    }

    public function down(Connection $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS two_factor_codes');
        $connection->exec('DROP TABLE IF EXISTS users');
    }

    public function getVersion(): string
    {
        return '001';
    }

    public function getDescription(): string
    {
        return 'Create users and two_factor_codes tables';
    }
}
