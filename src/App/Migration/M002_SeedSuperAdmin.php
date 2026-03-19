<?php

declare(strict_types=1);

namespace App\Migration;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class M002_SeedSuperAdmin implements MigrationInterface
{
    public function up(Connection $connection): void
    {
        // Créer le super admin par défaut (mot de passe: admin123)
        $hash = password_hash('admin123', PASSWORD_ARGON2ID);

        $connection->execute(
            'INSERT OR IGNORE INTO users (email, password_hash, first_name, last_name, roles) VALUES (:e, :p, :fn, :ln, :r)',
            [
                'e' => 'admin@rlsq-fram.local',
                'p' => $hash,
                'fn' => 'Super',
                'ln' => 'Admin',
                'r' => '["ROLE_SUPER_ADMIN","ROLE_ADMIN","ROLE_USER"]',
            ],
        );
    }

    public function down(Connection $connection): void
    {
        $connection->execute('DELETE FROM users WHERE email = :e', ['e' => 'admin@rlsq-fram.local']);
    }

    public function getVersion(): string
    {
        return '002';
    }

    public function getDescription(): string
    {
        return 'Seed default super admin user';
    }
}
