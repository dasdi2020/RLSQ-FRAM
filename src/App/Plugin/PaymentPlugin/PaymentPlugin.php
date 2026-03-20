<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin;

use RLSQ\Database\Connection;
use RLSQ\Plugin\AbstractPlugin;

class PaymentPlugin extends AbstractPlugin
{
    public function getName(): string { return 'Paiements'; }
    public function getSlug(): string { return 'payments'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Système de paiement multi-providers : Stripe, PayPal, Moneris, Global Payments. Gestion des remboursements et abonnements.'; }
    public function getIcon(): string { return 'credit-card'; }

    public function install(Connection $c): void
    {
        $c->exec('
            CREATE TABLE IF NOT EXISTS payment_gateway_configs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                gateway VARCHAR(50) NOT NULL,
                credentials TEXT DEFAULT "{}",
                is_active BOOLEAN DEFAULT 0,
                is_default BOOLEAN DEFAULT 0,
                settings TEXT DEFAULT "{}",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                gateway_config_id INTEGER,
                gateway VARCHAR(50) NOT NULL,
                external_id VARCHAR(255),
                amount REAL NOT NULL,
                currency VARCHAR(3) DEFAULT "CAD",
                status VARCHAR(30) DEFAULT "pending",
                payer_email VARCHAR(255),
                payer_name VARCHAR(255),
                member_id INTEGER,
                module_source VARCHAR(50),
                module_item_id INTEGER,
                description TEXT,
                metadata TEXT DEFAULT "{}",
                checkout_url VARCHAR(1000),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME DEFAULT NULL,
                FOREIGN KEY (gateway_config_id) REFERENCES payment_gateway_configs(id)
            )
        ');
        $c->exec('CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status)');
        $c->exec('CREATE INDEX IF NOT EXISTS idx_payments_module ON payments(module_source, module_item_id)');

        $c->exec('
            CREATE TABLE IF NOT EXISTS refunds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_id INTEGER NOT NULL,
                external_id VARCHAR(255),
                amount REAL NOT NULL,
                reason TEXT,
                status VARCHAR(30) DEFAULT "pending",
                processed_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME DEFAULT NULL,
                FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
            )
        ');

        $c->exec('
            CREATE TABLE IF NOT EXISTS subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                member_id INTEGER,
                gateway_config_id INTEGER,
                gateway VARCHAR(50) NOT NULL,
                external_id VARCHAR(255),
                plan_name VARCHAR(255),
                amount REAL NOT NULL,
                currency VARCHAR(3) DEFAULT "CAD",
                interval_type VARCHAR(20) DEFAULT "monthly",
                status VARCHAR(30) DEFAULT "active",
                current_period_start DATETIME,
                current_period_end DATETIME,
                auto_renew BOOLEAN DEFAULT 1,
                module_source VARCHAR(50),
                module_item_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                cancelled_at DATETIME DEFAULT NULL,
                FOREIGN KEY (gateway_config_id) REFERENCES payment_gateway_configs(id)
            )
        ');
    }

    public function uninstall(Connection $c): void
    {
        $c->exec('DROP TABLE IF EXISTS subscriptions');
        $c->exec('DROP TABLE IF EXISTS refunds');
        $c->exec('DROP TABLE IF EXISTS payments');
        $c->exec('DROP TABLE IF EXISTS payment_gateway_configs');
    }

    public function getMenuItems(): array
    {
        return [
            ['label' => 'Paiements', 'icon' => 'credit-card', 'path' => '/payments'],
            ['label' => 'Abonnements', 'icon' => 'repeat', 'path' => '/payments/subscriptions'],
            ['label' => 'Config paiement', 'icon' => 'settings', 'path' => '/payments/config'],
        ];
    }

    public function getSettingsSchema(): array
    {
        return ['fields' => [
            ['name' => 'default_currency', 'type' => 'string', 'label' => 'Devise par défaut', 'default' => 'CAD'],
            ['name' => 'allow_refunds', 'type' => 'boolean', 'label' => 'Autoriser les remboursements', 'default' => true],
            ['name' => 'refund_deadline_days', 'type' => 'integer', 'label' => 'Délai de remboursement (jours)', 'default' => 30],
            ['name' => 'auto_renewal', 'type' => 'boolean', 'label' => 'Renouvellement automatique par défaut', 'default' => true],
        ]];
    }
}
