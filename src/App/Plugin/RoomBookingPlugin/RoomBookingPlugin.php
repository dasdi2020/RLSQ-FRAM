<?php

declare(strict_types=1);

namespace App\Plugin\RoomBookingPlugin;

use RLSQ\Database\Connection;
use RLSQ\Plugin\AbstractPlugin;

class RoomBookingPlugin extends AbstractPlugin
{
    public function getName(): string { return 'Location de salles'; }
    public function getSlug(): string { return 'room-booking'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Réservation de salles et espaces avec disponibilités et tarification.'; }
    public function getIcon(): string { return 'door-open'; }

    public function install(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                capacity INTEGER DEFAULT 0,
                hourly_rate REAL DEFAULT 0,
                half_day_rate REAL DEFAULT 0,
                full_day_rate REAL DEFAULT 0,
                currency VARCHAR(3) DEFAULT "CAD",
                amenities TEXT DEFAULT "[]",
                image_url VARCHAR(500),
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS room_bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                member_id INTEGER,
                title VARCHAR(255),
                start_at DATETIME NOT NULL,
                end_at DATETIME NOT NULL,
                status VARCHAR(30) DEFAULT "pending",
                total_price REAL DEFAULT 0,
                payment_status VARCHAR(30) DEFAULT "unpaid",
                payment_id INTEGER,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
            )
        ');
    }

    public function uninstall(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS room_bookings');
        $c->exec('DROP TABLE IF EXISTS rooms');
    }

    public function getMenuItems(): array
    {
        return [
            ['label' => 'Salles', 'icon' => 'door-open', 'path' => '/rooms'],
            ['label' => 'Réservations', 'icon' => 'calendar-clock', 'path' => '/rooms/bookings'],
        ];
    }

    public function getSettingsSchema(): array
    {
        return ['fields' => [
            ['name' => 'require_approval', 'type' => 'boolean', 'label' => 'Approbation requise', 'default' => true],
            ['name' => 'min_booking_hours', 'type' => 'integer', 'label' => 'Durée minimum (heures)', 'default' => 1],
            ['name' => 'max_advance_days', 'type' => 'integer', 'label' => 'Réservation max jours à l\'avance', 'default' => 90],
        ]];
    }
}
