<?php

declare(strict_types=1);

namespace RLSQ\Database;

/**
 * Wrapping de PDO avec des méthodes utilitaires.
 */
class Connection
{
    private \PDO $pdo;

    public function __construct(
        private readonly string $dsn,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly array $options = [],
    ) {
        $defaultOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new \PDO(
            $this->dsn,
            $this->username,
            $this->password,
            array_replace($defaultOptions, $this->options),
        );
    }

    /**
     * Crée une connexion depuis un tableau de config.
     */
    public static function create(array $config): static
    {
        $driver = $config['driver'] ?? 'sqlite';
        $dsn = match ($driver) {
            'sqlite' => 'sqlite:' . ($config['path'] ?? ':memory:'),
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? '3306',
                $config['dbname'] ?? '',
                $config['charset'] ?? 'utf8mb4',
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? '5432',
                $config['dbname'] ?? '',
            ),
            default => throw new \InvalidArgumentException(sprintf('Driver "%s" non supporté.', $driver)),
        };

        return new static($dsn, $config['user'] ?? null, $config['password'] ?? null);
    }

    public function query(string $sql): \PDOStatement
    {
        return $this->pdo->query($sql);
    }

    public function prepare(string $sql): \PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Exécute une requête préparée avec des paramètres.
     */
    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->execute($sql, $params)->fetchAll();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->execute($sql, $params)->fetch();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return $this->execute($sql, $params)->fetchColumn($column);
    }

    public function exec(string $sql): int
    {
        return (int) $this->pdo->exec($sql);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Exécute un callable dans une transaction.
     */
    public function transactional(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
