<?php

declare(strict_types=1);

namespace App\AuditLog;

use RLSQ\Database\Connection;

class AuditLogger
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function log(string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, ?array $changes = null, ?string $ip = null): void
    {
        $this->connection->execute(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, changes, ip_address) VALUES (:u, :a, :et, :ei, :c, :ip)',
            ['u' => $userId, 'a' => $action, 'et' => $entityType, 'ei' => $entityId, 'c' => $changes ? json_encode($changes) : null, 'ip' => $ip],
        );
    }

    /** @return array{data: array[], total: int} */
    public function query(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];

        foreach (['action', 'entity_type', 'user_id'] as $f) {
            if (!empty($filters[$f])) { $where[] = "{$f} = :{$f}"; $params[$f] = $filters[$f]; }
        }
        if (!empty($filters['from'])) { $where[] = 'created_at >= :from'; $params['from'] = $filters['from']; }
        if (!empty($filters['to'])) { $where[] = 'created_at <= :to'; $params['to'] = $filters['to']; }

        $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM audit_logs{$whereSql}", $params);
        $offset = ($page - 1) * $perPage;

        $rows = $this->connection->fetchAll("SELECT * FROM audit_logs{$whereSql} ORDER BY created_at DESC LIMIT :l OFFSET :o", array_merge($params, ['l' => $perPage, 'o' => $offset]));
        foreach ($rows as &$r) { $r['changes'] = json_decode($r['changes'] ?? 'null', true); }

        return ['data' => $rows, 'total' => $total, 'page' => $page];
    }
}
