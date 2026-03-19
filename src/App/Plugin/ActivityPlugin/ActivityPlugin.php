<?php

declare(strict_types=1);

namespace App\Plugin\ActivityPlugin;

use RLSQ\Database\Connection;
use RLSQ\Plugin\AbstractPlugin;

class ActivityPlugin extends AbstractPlugin
{
    public function getName(): string { return 'Activités'; }
    public function getSlug(): string { return 'activities'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Gestion des activités, événements et sessions récurrentes avec inscription des membres.'; }
    public function getIcon(): string { return 'calendar-check'; }

    public function install(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100),
                location VARCHAR(255),
                max_capacity INTEGER DEFAULT 0,
                price REAL DEFAULT 0,
                currency VARCHAR(3) DEFAULT "CAD",
                is_recurring BOOLEAN DEFAULT 0,
                recurrence_rule TEXT,
                start_date DATETIME,
                end_date DATETIME,
                status VARCHAR(30) DEFAULT "draft",
                image_url VARCHAR(500),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS activity_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                activity_id INTEGER NOT NULL,
                start_at DATETIME NOT NULL,
                end_at DATETIME,
                location VARCHAR(255),
                instructor VARCHAR(255),
                notes TEXT,
                is_cancelled BOOLEAN DEFAULT 0,
                FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS activity_registrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                activity_id INTEGER NOT NULL,
                member_id INTEGER,
                email VARCHAR(255) NOT NULL,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                status VARCHAR(30) DEFAULT "registered",
                payment_status VARCHAR(30) DEFAULT "unpaid",
                payment_id INTEGER,
                registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
            )
        ');
    }

    public function uninstall(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS activity_registrations');
        $c->exec('DROP TABLE IF EXISTS activity_sessions');
        $c->exec('DROP TABLE IF EXISTS activities');
    }

    public function getMenuItems(): array
    {
        return [
            ['label' => 'Activités', 'icon' => 'calendar-check', 'path' => '/activities'],
        ];
    }

    public function getSettingsSchema(): array
    {
        return ['fields' => [
            ['name' => 'require_payment', 'type' => 'boolean', 'label' => 'Paiement obligatoire', 'default' => false],
            ['name' => 'allow_waitlist', 'type' => 'boolean', 'label' => 'Liste d\'attente', 'default' => true],
        ]];
    }
}
