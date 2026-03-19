<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;

class TenantDashboardMigration implements MigrationInterface
{
    public function up(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS dashboards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT "custom",
                target_roles TEXT DEFAULT "[]",
                layout TEXT DEFAULT "{}",
                is_default BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS dashboard_widgets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                dashboard_id INTEGER NOT NULL,
                widget_type VARCHAR(50) NOT NULL,
                title VARCHAR(255) DEFAULT "",
                config TEXT DEFAULT "{}",
                position_x INTEGER DEFAULT 0,
                position_y INTEGER DEFAULT 0,
                width INTEGER DEFAULT 1,
                height INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (dashboard_id) REFERENCES dashboards(id) ON DELETE CASCADE
            )
        ');

        // Dashboards par défaut
        $c->execute("INSERT INTO dashboards (name, type, target_roles, is_default) VALUES (:n, :t, :r, 1)", [
            'n' => 'Dashboard Organisation', 't' => 'federation', 'r' => '["ROLE_TENANT_ADMIN","ROLE_FEDERATION_ADMIN"]',
        ]);
        $fedId = (int) $c->lastInsertId();

        $c->execute("INSERT INTO dashboards (name, type, target_roles, is_default) VALUES (:n, :t, :r, 1)", [
            'n' => 'Dashboard Club', 't' => 'club', 'r' => '["ROLE_CLUB_ADMIN"]',
        ]);
        $clubId = (int) $c->lastInsertId();

        $c->execute("INSERT INTO dashboards (name, type, target_roles, is_default) VALUES (:n, :t, :r, 1)", [
            'n' => 'Espace Membre', 't' => 'member', 'r' => '["ROLE_MEMBER"]',
        ]);
        $memberId = (int) $c->lastInsertId();

        // Widgets par défaut pour le dashboard fédération
        $defaultWidgets = [
            [$fedId, 'counter', 'Membres', '{"source":"members","operation":"count"}', 0, 0, 1, 1, 0],
            [$fedId, 'counter', 'Clubs', '{"source":"clubs","operation":"count"}', 1, 0, 1, 1, 1],
            [$fedId, 'counter', 'Plugins actifs', '{"source":"_plugin_state","operation":"count","filter":{"is_active":1}}', 2, 0, 1, 1, 2],
            [$fedId, 'counter', 'Événements', '{"source":"audit_logs","operation":"count"}', 3, 0, 1, 1, 3],
            [$fedId, 'datatable', 'Derniers membres', '{"source":"members","limit":5,"sort":"-created_at","columns":["first_name","last_name","email","status"]}', 0, 1, 2, 2, 4],
            [$fedId, 'datatable', 'Derniers clubs', '{"source":"clubs","limit":5,"sort":"-created_at","columns":["name","email","is_active"]}', 2, 1, 2, 2, 5],
        ];

        foreach ($defaultWidgets as $w) {
            $c->execute(
                'INSERT INTO dashboard_widgets (dashboard_id, widget_type, title, config, position_x, position_y, width, height, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $w,
            );
        }

        // Widgets pour le dashboard club
        $c->execute('INSERT INTO dashboard_widgets (dashboard_id, widget_type, title, config, position_x, position_y, width, height, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$clubId, 'counter', 'Mes membres', '{"source":"members","operation":"count"}', 0, 0, 1, 1, 0]);
        $c->execute('INSERT INTO dashboard_widgets (dashboard_id, widget_type, title, config, position_x, position_y, width, height, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$clubId, 'datatable', 'Membres du club', '{"source":"members","limit":10,"columns":["first_name","last_name","email"]}', 0, 1, 4, 2, 1]);

        // Widgets pour le dashboard membre
        $c->execute('INSERT INTO dashboard_widgets (dashboard_id, widget_type, title, config, position_x, position_y, width, height, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$memberId, 'welcome', 'Bienvenue', '{"message":"Bienvenue dans votre espace membre."}', 0, 0, 4, 1, 0]);
    }

    public function down(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS dashboard_widgets');
        $c->exec('DROP TABLE IF EXISTS dashboards');
    }

    public function getVersion(): string
    {
        return 'T003';
    }

    public function getDescription(): string
    {
        return 'Create dashboards and widgets tables with defaults';
    }
}
