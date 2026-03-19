<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;

class ConnectionTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');
        $this->conn->exec('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }

    public function testExecAndQuery(): void
    {
        $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");
        $this->conn->exec("INSERT INTO test (name) VALUES ('Bob')");

        $rows = $this->conn->fetchAll('SELECT * FROM test');

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testPreparedStatement(): void
    {
        $this->conn->execute('INSERT INTO test (name) VALUES (:name)', ['name' => 'Charlie']);

        $row = $this->conn->fetchOne('SELECT * FROM test WHERE name = :name', ['name' => 'Charlie']);

        $this->assertSame('Charlie', $row['name']);
    }

    public function testLastInsertId(): void
    {
        $this->conn->execute('INSERT INTO test (name) VALUES (:name)', ['name' => 'Dave']);

        $id = $this->conn->lastInsertId();

        $this->assertSame('1', $id);
    }

    public function testFetchColumn(): void
    {
        $this->conn->execute('INSERT INTO test (name) VALUES (:name)', ['name' => 'Eve']);

        $count = $this->conn->fetchColumn('SELECT COUNT(*) FROM test');

        $this->assertSame(1, (int) $count);
    }

    public function testTransaction(): void
    {
        $this->conn->beginTransaction();
        $this->conn->execute('INSERT INTO test (name) VALUES (:name)', ['name' => 'Tx']);
        $this->conn->commit();

        $this->assertSame(1, (int) $this->conn->fetchColumn('SELECT COUNT(*) FROM test'));
    }

    public function testRollback(): void
    {
        $this->conn->beginTransaction();
        $this->conn->execute('INSERT INTO test (name) VALUES (:name)', ['name' => 'Gone']);
        $this->conn->rollback();

        $this->assertSame(0, (int) $this->conn->fetchColumn('SELECT COUNT(*) FROM test'));
    }

    public function testTransactional(): void
    {
        $result = $this->conn->transactional(function (Connection $conn) {
            $conn->execute('INSERT INTO test (name) VALUES (:name)', ['name' => 'Lambda']);
            return 42;
        });

        $this->assertSame(42, $result);
        $this->assertSame(1, (int) $this->conn->fetchColumn('SELECT COUNT(*) FROM test'));
    }

    public function testTransactionalRollbackOnException(): void
    {
        try {
            $this->conn->transactional(function (Connection $conn) {
                $conn->execute('INSERT INTO test (name) VALUES (:name)', ['name' => 'Fail']);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, (int) $this->conn->fetchColumn('SELECT COUNT(*) FROM test'));
    }

    public function testCreateFromConfig(): void
    {
        $conn = Connection::create(['driver' => 'sqlite', 'path' => ':memory:']);

        $conn->exec('CREATE TABLE t (id INTEGER)');
        $conn->exec('INSERT INTO t VALUES (1)');

        $this->assertSame(1, (int) $conn->fetchColumn('SELECT COUNT(*) FROM t'));
    }
}
