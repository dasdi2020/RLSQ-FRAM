<?php

declare(strict_types=1);

namespace App\Webhook;

use RLSQ\Database\Connection;

class WebhookService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        $this->ensureTable();
    }

    public function register(string $url, array $events, string $secret = ''): array
    {
        if (!$secret) { $secret = bin2hex(random_bytes(16)); }

        $this->connection->execute(
            'INSERT INTO webhook_endpoints (url, events, secret, is_active) VALUES (:u, :e, :s, 1)',
            ['u' => $url, 'e' => json_encode($events), 's' => $secret],
        );

        return $this->get((int) $this->connection->lastInsertId());
    }

    public function get(int $id): ?array
    {
        $r = $this->connection->fetchOne('SELECT * FROM webhook_endpoints WHERE id = :id', ['id' => $id]);
        if ($r) { $r['events'] = json_decode($r['events'] ?? '[]', true); }
        return $r ?: null;
    }

    /** @return array[] */
    public function getAll(): array
    {
        $rows = $this->connection->fetchAll('SELECT * FROM webhook_endpoints ORDER BY id');
        foreach ($rows as &$r) { $r['events'] = json_decode($r['events'] ?? '[]', true); }
        return $rows;
    }

    public function delete(int $id): void
    {
        $this->connection->execute('DELETE FROM webhook_endpoints WHERE id = :id', ['id' => $id]);
    }

    /**
     * Dispatche un événement à tous les endpoints abonnés.
     */
    public function dispatch(string $event, array $payload): array
    {
        $endpoints = $this->connection->fetchAll('SELECT * FROM webhook_endpoints WHERE is_active = 1');
        $results = [];

        foreach ($endpoints as $ep) {
            $events = json_decode($ep['events'] ?? '[]', true);
            if (!in_array($event, $events, true) && !in_array('*', $events, true)) {
                continue;
            }

            $body = json_encode(['event' => $event, 'data' => $payload, 'timestamp' => time()]);
            $signature = hash_hmac('sha256', $body, $ep['secret']);

            $responseCode = $this->send($ep['url'], $body, $signature);

            $this->connection->execute(
                'INSERT INTO webhook_deliveries (endpoint_id, event, payload, response_code, delivered_at, attempts) VALUES (:eid, :e, :p, :rc, :da, 1)',
                ['eid' => $ep['id'], 'e' => $event, 'p' => $body, 'rc' => $responseCode, 'da' => date('Y-m-d H:i:s')],
            );

            $results[] = ['endpoint_id' => $ep['id'], 'url' => $ep['url'], 'status' => $responseCode];
        }

        return $results;
    }

    /** @return array[] */
    public function getDeliveries(int $endpointId, int $limit = 20): array
    {
        return $this->connection->fetchAll(
            'SELECT * FROM webhook_deliveries WHERE endpoint_id = :eid ORDER BY delivered_at DESC LIMIT :l',
            ['eid' => $endpointId, 'l' => $limit],
        );
    }

    private function send(string $url, string $body, string $signature): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Webhook-Signature: ' . $signature],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code;
    }

    private function ensureTable(): void
    {
        $this->connection->exec('CREATE TABLE IF NOT EXISTS webhook_endpoints (id INTEGER PRIMARY KEY AUTOINCREMENT, url VARCHAR(500) NOT NULL, events TEXT DEFAULT "[]", secret VARCHAR(64), is_active BOOLEAN DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE IF NOT EXISTS webhook_deliveries (id INTEGER PRIMARY KEY AUTOINCREMENT, endpoint_id INTEGER, event VARCHAR(100), payload TEXT, response_code INTEGER, delivered_at DATETIME, attempts INTEGER DEFAULT 0, FOREIGN KEY (endpoint_id) REFERENCES webhook_endpoints(id) ON DELETE CASCADE)');
    }
}
