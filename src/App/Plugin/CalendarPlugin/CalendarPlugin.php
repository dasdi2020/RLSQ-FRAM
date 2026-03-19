<?php

declare(strict_types=1);

namespace App\Plugin\CalendarPlugin;

use RLSQ\Database\Connection;
use RLSQ\Plugin\AbstractPlugin;

class CalendarPlugin extends AbstractPlugin
{
    public function getName(): string { return 'Calendrier'; }
    public function getSlug(): string { return 'calendar'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Calendrier partagé pour visualiser les formations, activités et événements.'; }
    public function getIcon(): string { return 'calendar'; }

    public function install(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS calendar_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                start_at DATETIME NOT NULL,
                end_at DATETIME,
                all_day BOOLEAN DEFAULT 0,
                color VARCHAR(20) DEFAULT "#ff3e00",
                location VARCHAR(255),
                source_type VARCHAR(50),
                source_id INTEGER,
                is_public BOOLEAN DEFAULT 1,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    public function uninstall(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS calendar_events');
    }

    public function getMenuItems(): array
    {
        return [['label' => 'Calendrier', 'icon' => 'calendar', 'path' => '/calendar']];
    }
}
