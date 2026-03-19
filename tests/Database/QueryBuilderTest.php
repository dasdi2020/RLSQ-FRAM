<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\QueryBuilder;

class QueryBuilderTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');
    }

    private function qb(): QueryBuilder
    {
        return new QueryBuilder($this->conn);
    }

    // --- SELECT ---

    public function testSimpleSelect(): void
    {
        $sql = $this->qb()->select('*')->from('users')->getSQL();

        $this->assertSame('SELECT * FROM users', $sql);
    }

    public function testSelectColumns(): void
    {
        $sql = $this->qb()->select('id', 'name')->from('users', 'u')->getSQL();

        $this->assertSame('SELECT id, name FROM users u', $sql);
    }

    public function testWhere(): void
    {
        $sql = $this->qb()->select('*')->from('users')->where('id = :id')->getSQL();

        $this->assertSame('SELECT * FROM users WHERE id = :id', $sql);
    }

    public function testAndWhere(): void
    {
        $sql = $this->qb()
            ->select('*')->from('users')
            ->where('active = 1')
            ->andWhere('age > 18')
            ->getSQL();

        $this->assertSame('SELECT * FROM users WHERE active = 1 AND age > 18', $sql);
    }

    public function testOrderBy(): void
    {
        $sql = $this->qb()
            ->select('*')->from('users')
            ->orderBy('name', 'ASC')
            ->addOrderBy('id', 'DESC')
            ->getSQL();

        $this->assertSame('SELECT * FROM users ORDER BY name ASC, id DESC', $sql);
    }

    public function testLimitOffset(): void
    {
        $sql = $this->qb()
            ->select('*')->from('users')
            ->setMaxResults(10)
            ->setFirstResult(20)
            ->getSQL();

        $this->assertSame('SELECT * FROM users LIMIT 10 OFFSET 20', $sql);
    }

    public function testJoin(): void
    {
        $sql = $this->qb()
            ->select('u.name', 'p.title')
            ->from('users', 'u')
            ->join('posts', 'p', 'p.user_id = u.id')
            ->getSQL();

        $this->assertSame('SELECT u.name, p.title FROM users u INNER JOIN posts p ON p.user_id = u.id', $sql);
    }

    public function testLeftJoin(): void
    {
        $sql = $this->qb()
            ->select('*')
            ->from('users', 'u')
            ->leftJoin('profiles', 'p', 'p.user_id = u.id')
            ->getSQL();

        $this->assertStringContainsString('LEFT JOIN', $sql);
    }

    public function testGroupByHaving(): void
    {
        $sql = $this->qb()
            ->select('status', 'COUNT(*) as cnt')
            ->from('orders')
            ->groupBy('status')
            ->having('cnt > 5')
            ->getSQL();

        $this->assertSame('SELECT status, COUNT(*) as cnt FROM orders GROUP BY status HAVING cnt > 5', $sql);
    }

    // --- INSERT ---

    public function testInsert(): void
    {
        $sql = $this->qb()
            ->insert('users')
            ->values(['name' => ':name', 'email' => ':email'])
            ->getSQL();

        $this->assertSame('INSERT INTO users (name, email) VALUES (:name, :email)', $sql);
    }

    // --- UPDATE ---

    public function testUpdate(): void
    {
        $sql = $this->qb()
            ->update('users')
            ->set('name', ':name')
            ->set('active', '1')
            ->where('id = :id')
            ->getSQL();

        $this->assertSame('UPDATE users SET name = :name, active = 1 WHERE id = :id', $sql);
    }

    // --- DELETE ---

    public function testDelete(): void
    {
        $sql = $this->qb()
            ->delete('users')
            ->where('id = :id')
            ->getSQL();

        $this->assertSame('DELETE FROM users WHERE id = :id', $sql);
    }

    // --- Execute ---

    public function testExecuteQuery(): void
    {
        $this->conn->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $this->conn->exec("INSERT INTO items VALUES (1, 'A')");
        $this->conn->exec("INSERT INTO items VALUES (2, 'B')");

        $rows = $this->qb()
            ->select('*')
            ->from('items')
            ->orderBy('id')
            ->fetchAllAssociative();

        $this->assertCount(2, $rows);
        $this->assertSame('A', $rows[0]['name']);
    }
}
