<?php

declare(strict_types=1);

namespace App\Plugin\FormationPlugin;

use RLSQ\Database\Connection;
use RLSQ\Plugin\AbstractPlugin;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

class FormationPlugin extends AbstractPlugin
{
    public function getName(): string { return 'Formations'; }
    public function getSlug(): string { return 'formations'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Gestion des formations avec inscription et paiement. Les membres peuvent parcourir, s\'inscrire et payer en ligne.'; }
    public function getIcon(): string { return 'book-open'; }

    public function install(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS formations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                instructor VARCHAR(255),
                location VARCHAR(255),
                max_capacity INTEGER DEFAULT 0,
                price REAL DEFAULT 0,
                currency VARCHAR(3) DEFAULT "CAD",
                start_date DATETIME,
                end_date DATETIME,
                registration_deadline DATETIME,
                status VARCHAR(30) DEFAULT "draft",
                image_url VARCHAR(500),
                settings TEXT DEFAULT "{}",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS formation_registrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                formation_id INTEGER NOT NULL,
                member_id INTEGER,
                email VARCHAR(255) NOT NULL,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                phone VARCHAR(50),
                status VARCHAR(30) DEFAULT "pending",
                payment_status VARCHAR(30) DEFAULT "unpaid",
                payment_id INTEGER,
                amount_paid REAL DEFAULT 0,
                notes TEXT,
                registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
            )
        ');
        $c->exec('CREATE INDEX IF NOT EXISTS idx_reg_formation ON formation_registrations(formation_id)');
    }

    public function uninstall(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS formation_registrations');
        $c->exec('DROP TABLE IF EXISTS formations');
    }

    public function getMenuItems(): array
    {
        return [
            ['label' => 'Formations', 'icon' => 'book-open', 'path' => '/formations'],
            ['label' => 'Inscriptions', 'icon' => 'users', 'path' => '/formations/registrations'],
        ];
    }

    public function getSettingsSchema(): array
    {
        return ['fields' => [
            ['name' => 'require_payment', 'type' => 'boolean', 'label' => 'Paiement obligatoire', 'default' => true],
            ['name' => 'require_auth', 'type' => 'boolean', 'label' => 'Authentification requise pour l\'inscription', 'default' => false],
            ['name' => 'confirmation_email', 'type' => 'boolean', 'label' => 'Envoyer un email de confirmation', 'default' => true],
            ['name' => 'max_registrations_per_member', 'type' => 'integer', 'label' => 'Max inscriptions par membre', 'default' => 0],
        ]];
    }
}
