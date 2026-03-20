<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Service;

use RLSQ\Database\Connection;

class PaymentService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Crée un paiement et initie le checkout.
     */
    public function createCheckout(array $data): array
    {
        $gateway = $this->resolveGateway($data['gateway'] ?? null);
        $credentials = json_decode($gateway['credentials'] ?? '{}', true);
        $gw = GatewayFactory::create($gateway['gateway'], $credentials);

        $amount = (float) ($data['amount'] ?? 0);
        $currency = $data['currency'] ?? json_decode($gateway['settings'] ?? '{}', true)['default_currency'] ?? 'CAD';

        $checkoutResult = $gw->createCheckout([
            'amount' => $amount,
            'currency' => $currency,
            'description' => $data['description'] ?? 'Paiement',
            'success_url' => $data['success_url'] ?? '',
            'cancel_url' => $data['cancel_url'] ?? '',
        ]);

        // Enregistrer le paiement
        $this->connection->execute(
            'INSERT INTO payments (gateway_config_id, gateway, external_id, amount, currency, status, payer_email, payer_name, member_id, module_source, module_item_id, description, checkout_url, metadata)
             VALUES (:gcid, :gw, :eid, :amt, :cur, :st, :pe, :pn, :mid, :ms, :mi, :desc, :curl, :meta)',
            [
                'gcid' => $gateway['id'], 'gw' => $gateway['gateway'],
                'eid' => $checkoutResult['external_id'] ?? null,
                'amt' => $amount, 'cur' => $currency, 'st' => 'pending',
                'pe' => $data['payer_email'] ?? null, 'pn' => $data['payer_name'] ?? null,
                'mid' => $data['member_id'] ?? null,
                'ms' => $data['module_source'] ?? null, 'mi' => $data['module_item_id'] ?? null,
                'desc' => $data['description'] ?? null,
                'curl' => $checkoutResult['checkout_url'] ?? null,
                'meta' => json_encode($data['metadata'] ?? []),
            ],
        );

        $paymentId = (int) $this->connection->lastInsertId();

        return [
            'payment_id' => $paymentId,
            'checkout_url' => $checkoutResult['checkout_url'] ?? null,
            'external_id' => $checkoutResult['external_id'] ?? null,
            'status' => 'pending',
        ];
    }

    /**
     * Confirme un paiement après callback.
     */
    public function confirm(int $paymentId): array
    {
        $payment = $this->getPayment($paymentId);
        if (!$payment) {
            throw new \RuntimeException('Paiement introuvable.');
        }

        $gateway = $this->getGatewayConfig((int) $payment['gateway_config_id']);
        $credentials = json_decode($gateway['credentials'] ?? '{}', true);
        $gw = GatewayFactory::create($payment['gateway'], $credentials);

        $result = $gw->capturePayment($payment['external_id']);

        $newStatus = $result['status'] ?? 'failed';
        $this->connection->execute(
            'UPDATE payments SET status = :s, completed_at = :ca WHERE id = :id',
            ['s' => $newStatus, 'ca' => $newStatus === 'completed' ? date('Y-m-d H:i:s') : null, 'id' => $paymentId],
        );

        return $this->getPayment($paymentId);
    }

    public function getPayment(int $id): ?array
    {
        $p = $this->connection->fetchOne('SELECT * FROM payments WHERE id = :id', ['id' => $id]);
        if ($p) {
            $p['metadata'] = json_decode($p['metadata'] ?? '{}', true);
        }

        return $p ?: null;
    }

    /**
     * @return array{data: array[], total: int}
     */
    public function getPayments(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['module_source'])) {
            $where[] = 'module_source = :ms';
            $params['ms'] = $filters['module_source'];
        }

        $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM payments{$whereSql}", $params);

        $offset = ($page - 1) * $perPage;
        $rows = $this->connection->fetchAll(
            "SELECT * FROM payments{$whereSql} ORDER BY created_at DESC LIMIT :lim OFFSET :off",
            array_merge($params, ['lim' => $perPage, 'off' => $offset]),
        );

        foreach ($rows as &$r) {
            $r['metadata'] = json_decode($r['metadata'] ?? '{}', true);
        }

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * Statistiques rapides des paiements.
     */
    public function getStats(): array
    {
        return [
            'total_revenue' => (float) ($this->connection->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'") ?? 0),
            'total_payments' => (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM payments'),
            'completed' => (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM payments WHERE status = 'completed'"),
            'pending' => (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
            'failed' => (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM payments WHERE status = 'failed'"),
            'refunded' => (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM payments WHERE status IN ('refunded','partially_refunded')"),
        ];
    }

    // ==================== GATEWAY CONFIG ====================

    public function saveGatewayConfig(array $data): array
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM payment_gateway_configs WHERE gateway = :gw',
            ['gw' => $data['gateway']],
        );

        if ($existing) {
            $this->connection->execute(
                'UPDATE payment_gateway_configs SET credentials = :c, is_active = :a, is_default = :d, settings = :s, updated_at = :now WHERE id = :id',
                [
                    'id' => $existing['id'],
                    'c' => json_encode($data['credentials'] ?? []),
                    'a' => ($data['is_active'] ?? false) ? 1 : 0,
                    'd' => ($data['is_default'] ?? false) ? 1 : 0,
                    's' => json_encode($data['settings'] ?? []),
                    'now' => date('Y-m-d H:i:s'),
                ],
            );

            return $this->getGatewayConfig((int) $existing['id']);
        }

        $this->connection->execute(
            'INSERT INTO payment_gateway_configs (gateway, credentials, is_active, is_default, settings) VALUES (:gw, :c, :a, :d, :s)',
            [
                'gw' => $data['gateway'],
                'c' => json_encode($data['credentials'] ?? []),
                'a' => ($data['is_active'] ?? false) ? 1 : 0,
                'd' => ($data['is_default'] ?? false) ? 1 : 0,
                's' => json_encode($data['settings'] ?? []),
            ],
        );

        return $this->getGatewayConfig((int) $this->connection->lastInsertId());
    }

    public function getGatewayConfigs(): array
    {
        $rows = $this->connection->fetchAll('SELECT * FROM payment_gateway_configs ORDER BY gateway');

        foreach ($rows as &$r) {
            $r['settings'] = json_decode($r['settings'] ?? '{}', true);
            // Ne pas exposer les credentials complets
            $creds = json_decode($r['credentials'] ?? '{}', true);
            $r['credentials_masked'] = array_map(fn ($v) => is_string($v) && strlen($v) > 8 ? substr($v, 0, 4) . '****' : $v, $creds);
            unset($r['credentials']);
        }

        return $rows;
    }

    public function getGatewayConfig(int $id): ?array
    {
        return $this->connection->fetchOne('SELECT * FROM payment_gateway_configs WHERE id = :id', ['id' => $id]) ?: null;
    }

    // ==================== REFUNDS ====================

    public function refund(int $paymentId, float $amount, string $reason = '', ?int $processedBy = null): array
    {
        $payment = $this->getPayment($paymentId);
        if (!$payment || !in_array($payment['status'], ['completed', 'partially_refunded'], true)) {
            throw new \RuntimeException('Ce paiement ne peut pas être remboursé.');
        }

        $gateway = $this->getGatewayConfig((int) $payment['gateway_config_id']);
        $credentials = json_decode($gateway['credentials'] ?? '{}', true);
        $gw = GatewayFactory::create($payment['gateway'], $credentials);

        $result = $gw->refund($payment['external_id'], $amount, $reason);

        $this->connection->execute(
            'INSERT INTO refunds (payment_id, external_id, amount, reason, status, processed_by, completed_at) VALUES (:pid, :eid, :amt, :r, :s, :pb, :ca)',
            [
                'pid' => $paymentId, 'eid' => $result['refund_id'] ?? null,
                'amt' => $amount, 'r' => $reason,
                's' => $result['status'] ?? 'completed',
                'pb' => $processedBy,
                'ca' => ($result['status'] ?? '') === 'completed' ? date('Y-m-d H:i:s') : null,
            ],
        );

        // Mettre à jour le statut du paiement
        $totalRefunded = (float) $this->connection->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE payment_id = :pid AND status = 'completed'",
            ['pid' => $paymentId],
        );

        $newStatus = $totalRefunded >= (float) $payment['amount'] ? 'refunded' : 'partially_refunded';
        $this->connection->execute('UPDATE payments SET status = :s WHERE id = :id', ['s' => $newStatus, 'id' => $paymentId]);

        return $this->connection->fetchOne('SELECT * FROM refunds WHERE id = :id', ['id' => (int) $this->connection->lastInsertId()]);
    }

    public function getRefunds(int $paymentId): array
    {
        return $this->connection->fetchAll('SELECT * FROM refunds WHERE payment_id = :pid ORDER BY created_at DESC', ['pid' => $paymentId]);
    }

    // ==================== SUBSCRIPTIONS ====================

    public function createSubscription(array $data): array
    {
        $gateway = $this->resolveGateway($data['gateway'] ?? null);
        $credentials = json_decode($gateway['credentials'] ?? '{}', true);
        $gw = GatewayFactory::create($gateway['gateway'], $credentials);

        $result = $gw->createSubscription($data);

        $now = date('Y-m-d H:i:s');
        $interval = $data['interval_type'] ?? 'monthly';
        $periodEnd = date('Y-m-d H:i:s', strtotime($interval === 'yearly' ? '+1 year' : '+1 month'));

        $this->connection->execute(
            'INSERT INTO subscriptions (member_id, gateway_config_id, gateway, external_id, plan_name, amount, currency, interval_type, status, current_period_start, current_period_end, auto_renew, module_source, module_item_id)
             VALUES (:mid, :gcid, :gw, :eid, :pn, :amt, :cur, :it, :st, :ps, :pe, :ar, :ms, :mi)',
            [
                'mid' => $data['member_id'] ?? null, 'gcid' => $gateway['id'], 'gw' => $gateway['gateway'],
                'eid' => $result['subscription_id'] ?? null,
                'pn' => $data['plan_name'] ?? 'Abonnement',
                'amt' => (float) ($data['amount'] ?? 0), 'cur' => $data['currency'] ?? 'CAD',
                'it' => $interval, 'st' => 'active',
                'ps' => $now, 'pe' => $periodEnd,
                'ar' => ($data['auto_renew'] ?? true) ? 1 : 0,
                'ms' => $data['module_source'] ?? null, 'mi' => $data['module_item_id'] ?? null,
            ],
        );

        return $this->connection->fetchOne('SELECT * FROM subscriptions WHERE id = :id', ['id' => (int) $this->connection->lastInsertId()]);
    }

    public function cancelSubscription(int $subscriptionId): array
    {
        $sub = $this->connection->fetchOne('SELECT * FROM subscriptions WHERE id = :id', ['id' => $subscriptionId]);
        if (!$sub) {
            throw new \RuntimeException('Abonnement introuvable.');
        }

        $gateway = $this->getGatewayConfig((int) $sub['gateway_config_id']);
        $credentials = json_decode($gateway['credentials'] ?? '{}', true);
        $gw = GatewayFactory::create($sub['gateway'], $credentials);

        if ($sub['external_id']) {
            $gw->cancelSubscription($sub['external_id']);
        }

        $this->connection->execute(
            'UPDATE subscriptions SET status = :s, cancelled_at = :ca, auto_renew = 0 WHERE id = :id',
            ['s' => 'cancelled', 'ca' => date('Y-m-d H:i:s'), 'id' => $subscriptionId],
        );

        return $this->connection->fetchOne('SELECT * FROM subscriptions WHERE id = :id', ['id' => $subscriptionId]);
    }

    public function getSubscriptions(int $page = 1, int $perPage = 20): array
    {
        $total = (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM subscriptions');
        $offset = ($page - 1) * $perPage;

        return [
            'data' => $this->connection->fetchAll("SELECT * FROM subscriptions ORDER BY created_at DESC LIMIT :lim OFFSET :off", ['lim' => $perPage, 'off' => $offset]),
            'total' => $total,
        ];
    }

    // ==================== PRIVATE ====================

    private function resolveGateway(?string $gatewayName): array
    {
        if ($gatewayName) {
            $gw = $this->connection->fetchOne('SELECT * FROM payment_gateway_configs WHERE gateway = :gw AND is_active = 1', ['gw' => $gatewayName]);
            if ($gw) {
                return $gw;
            }
        }

        // Fallback : gateway par défaut
        $gw = $this->connection->fetchOne('SELECT * FROM payment_gateway_configs WHERE is_default = 1 AND is_active = 1');
        if ($gw) {
            return $gw;
        }

        // Fallback : premier gateway actif
        $gw = $this->connection->fetchOne('SELECT * FROM payment_gateway_configs WHERE is_active = 1 LIMIT 1');
        if ($gw) {
            return $gw;
        }

        throw new \RuntimeException('Aucun gateway de paiement configuré.');
    }
}
