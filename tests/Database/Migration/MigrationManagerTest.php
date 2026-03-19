<?php

declare(strict_types=1);

namespace Tests\Database\Migration;

use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationInterface;
use RLSQ\Database\Migration\MigrationManager;

class MigrationManagerTest extends TestCase
{
    private Connection $conn;
    private MigrationManager $manager;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');
        $this->manager = new MigrationManager($this->conn);
    }

    public function testMigrationsTableCreated(): void
    {
        $result = $this->conn->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='_migrations'");

        $this->assertCount(1, $result);
    }

    public function testMigrate(): void
    {
        $this->manager->addMigration(new TestMigration001());
        $this->manager->addMigration(new TestMigration002());

        $count = $this->manager->migrate();

        $this->assertSame(2, $count);
        $this->assertSame(['001', '002'], $this->manager->getExecutedVersions());

        // La table a été créée
        $this->conn->execute('INSERT INTO test_items (name) VALUES (:n)', ['n' => 'hello']);
        $this->assertSame(1, (int) $this->conn->fetchColumn('SELECT COUNT(*) FROM test_items'));
    }

    public function testMigrateSkipsAlreadyExecuted(): void
    {
        $this->manager->addMigration(new TestMigration001());

        $this->assertSame(1, $this->manager->migrate());
        $this->assertSame(0, $this->manager->migrate()); // Rien à faire
    }

    public function testRollback(): void
    {
        $this->manager->addMigration(new TestMigration001());
        $this->manager->addMigration(new TestMigration002());

        $this->manager->migrate();
        $this->assertTrue($this->manager->rollback());
        $this->assertSame(['001'], $this->manager->getExecutedVersions());
    }

    public function testRollbackEmpty(): void
    {
        $this->assertFalse($this->manager->rollback());
    }

    public function testGetStatus(): void
    {
        $this->manager->addMigration(new TestMigration001());
        $this->manager->addMigration(new TestMigration002());
        $this->manager->addMigration(new TestMigration001()); // Already added, same version
        $this->manager->migrate();

        // Add a new migration without executing
        $this->manager->addMigration(new TestMigration003());

        $status = $this->manager->getStatus();

        $this->assertSame('executed', $status[0]['status']);
        $this->assertSame('executed', $status[1]['status']);
        $this->assertSame('pending', $status[2]['status']);
    }

    public function testGetPendingMigrations(): void
    {
        $this->manager->addMigration(new TestMigration001());
        $this->manager->addMigration(new TestMigration002());
        $this->manager->addMigration(new TestMigration003());

        $this->manager->migrate(); // Execute all

        $this->assertCount(0, $this->manager->getPendingMigrations());
    }
}

// --- Fixtures ---

class TestMigration001 implements MigrationInterface
{
    public function up(Connection $c): void { $c->exec('CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT)'); }
    public function down(Connection $c): void { $c->exec('DROP TABLE test_items'); }
    public function getVersion(): string { return '001'; }
    public function getDescription(): string { return 'Create test_items'; }
}

class TestMigration002 implements MigrationInterface
{
    public function up(Connection $c): void { $c->exec('CREATE TABLE test_logs (id INTEGER PRIMARY KEY, msg TEXT)'); }
    public function down(Connection $c): void { $c->exec('DROP TABLE test_logs'); }
    public function getVersion(): string { return '002'; }
    public function getDescription(): string { return 'Create test_logs'; }
}

class TestMigration003 implements MigrationInterface
{
    public function up(Connection $c): void { $c->exec('CREATE TABLE test_extra (id INTEGER PRIMARY KEY)'); }
    public function down(Connection $c): void { $c->exec('DROP TABLE test_extra'); }
    public function getVersion(): string { return '003'; }
    public function getDescription(): string { return 'Create test_extra'; }
}
