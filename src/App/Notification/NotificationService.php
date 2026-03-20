<?php

declare(strict_types=1);

namespace App\Notification;

use RLSQ\Database\Connection;

class NotificationService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        $this->ensureTable();
    }

    public function create(int $userId, string $type, string $title, string $body = '', array $data = []): array
    {
        $this->connection->execute(
            'INSERT INTO notifications (user_id, type, title, body, data) VALUES (:u, :t, :ti, :b, :d)',
            ['u' => $userId, 't' => $type, 'ti' => $title, 'b' => $body, 'd' => json_encode($data)],
        );
        return $this->get((int) $this->connection->lastInsertId());
    }

    public function get(int $id): ?array
    {
        $n = $this->connection->fetchOne('SELECT * FROM notifications WHERE id = :id', ['id' => $id]);
        if ($n) { $n['data'] = json_decode($n['data'] ?? '{}', true); }
        return $n ?: null;
    }

    /** @return array[] */
    public function getForUser(int $userId, bool $unreadOnly = false, int $limit = 20): array
    {
        $where = $unreadOnly ? ' AND is_read = 0' : '';
        $rows = $this->connection->fetchAll(
            "SELECT * FROM notifications WHERE user_id = :u{$where} ORDER BY created_at DESC LIMIT :l",
            ['u' => $userId, 'l' => $limit],
        );
        foreach ($rows as &$r) { $r['data'] = json_decode($r['data'] ?? '{}', true); }
        return $rows;
    }

    public function markAsRead(int $id): void
    {
        $this->connection->execute('UPDATE notifications SET is_read = 1 WHERE id = :id', ['id' => $id]);
    }

    public function markAllAsRead(int $userId): void
    {
        $this->connection->execute('UPDATE notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0', ['u' => $userId]);
    }

    public function getUnreadCount(int $userId): int
    {
        return (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0', ['u' => $userId]);
    }

    public function delete(int $id): void
    {
        $this->connection->execute('DELETE FROM notifications WHERE id = :id', ['id' => $id]);
    }

    /**
     * Génère un flux SSE (Server-Sent Events) pour les notifications temps réel.
     */
    public function streamSSE(int $userId, callable $output): void
    {
        $lastId = 0;

        while (true) {
            $news = $this->connection->fetchAll(
                'SELECT * FROM notifications WHERE user_id = :u AND id > :lid ORDER BY id ASC',
                ['u' => $userId, 'lid' => $lastId],
            );

            foreach ($news as $n) {
                $n['data'] = json_decode($n['data'] ?? '{}', true);
                $output("data: " . json_encode($n) . "\n\n");
                $lastId = (int) $n['id'];
            }

            if (connection_aborted()) { break; }
            sleep(2);
        }
    }

    private function ensureTable(): void
    {
        $this->connection->exec('CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type VARCHAR(50), title VARCHAR(255), body TEXT, data TEXT DEFAULT "{}", is_read BOOLEAN DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, is_read)');
    }
}
