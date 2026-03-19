<?php

declare(strict_types=1);

namespace Tests\Tenant;

use App\Migration\M001_CreateUsersTable;
use App\Migration\M002_SeedSuperAdmin;
use App\Migration\M003_CreateTenantsTable;
use App\Tenant\Database\TenantBaseMigration;
use App\Tenant\Database\TenantDatabaseProvisioner;
use App\Tenant\TenantContext;
use App\Tenant\TenantResolver;
use App\Tenant\TenantService;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class TenantServiceTest extends TestCase
{
    private Connection $conn;
    private TenantService $service;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/rlsq_tenant_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->conn = new Connection('sqlite::memory:');

        // Exécuter les migrations plateforme
        $mgr = new MigrationManager($this->conn);
        $mgr->addMigrations([
            new M001_CreateUsersTable(),
            new M002_SeedSuperAdmin(),
            new M003_CreateTenantsTable(),
        ]);
        $mgr->migrate();

        $provisioner = new TenantDatabaseProvisioner($this->tmpDir);
        $provisioner->addBaseMigration(new TenantBaseMigration());

        $this->service = new TenantService($this->conn, $provisioner, $this->tmpDir);
    }

    protected function tearDown(): void
    {
        // Cleanup
        $files = glob($this->tmpDir . '/var/tenants/*') ?: [];
        foreach ($files as $f) { unlink($f); }
        @rmdir($this->tmpDir . '/var/tenants');
        @rmdir($this->tmpDir . '/var');
        @rmdir($this->tmpDir);
    }

    // --- Create ---

    public function testCreateTenant(): void
    {
        $tenant = $this->service->create(['name' => 'Fédération Test', 'type' => 'federation']);

        $this->assertNotNull($tenant);
        $this->assertSame('federation-test', $tenant['slug']);
        $this->assertSame('Fédération Test', $tenant['name']);
        $this->assertSame('federation', $tenant['type']);
        $this->assertSame(1, (int) $tenant['is_active']);
        $this->assertSame(0, (int) $tenant['is_provisioned']);
    }

    public function testCreateWithCustomSlug(): void
    {
        $tenant = $this->service->create(['name' => 'Test', 'slug' => 'my-org']);

        $this->assertSame('my-org', $tenant['slug']);
    }

    public function testCreateDuplicateSlugThrows(): void
    {
        $this->service->create(['name' => 'First', 'slug' => 'unique']);

        $this->expectException(\RuntimeException::class);
        $this->service->create(['name' => 'Second', 'slug' => 'unique']);
    }

    public function testCreateWithOwner(): void
    {
        $tenant = $this->service->create([
            'name' => 'With Owner',
            'owner_user_id' => 1, // super admin from seed
        ]);

        $users = $this->service->getUsers((int) $tenant['id']);
        $this->assertCount(1, $users);
        $this->assertSame(1, (int) $users[0]['user_id']);
        $this->assertSame(1, (int) $users[0]['is_primary']);
    }

    // --- Find ---

    public function testFindById(): void
    {
        $created = $this->service->create(['name' => 'Find Me']);

        $found = $this->service->findById((int) $created['id']);
        $this->assertNotNull($found);
        $this->assertSame('Find Me', $found['name']);
    }

    public function testFindBySlug(): void
    {
        $this->service->create(['name' => 'Slug Test']);

        $found = $this->service->findBySlug('slug-test');
        $this->assertNotNull($found);
    }

    public function testFindAll(): void
    {
        $this->service->create(['name' => 'A']);
        $this->service->create(['name' => 'B']);
        $this->service->create(['name' => 'C']);

        $all = $this->service->findAll();
        $this->assertCount(3, $all);
        $this->assertSame(3, $this->service->count());
    }

    // --- Update ---

    public function testUpdate(): void
    {
        $tenant = $this->service->create(['name' => 'Original']);
        $updated = $this->service->update((int) $tenant['id'], ['name' => 'Updated', 'primary_color' => '#00ff00']);

        $this->assertSame('Updated', $updated['name']);
        $this->assertSame('#00ff00', $updated['primary_color']);
    }

    // --- Delete (soft) ---

    public function testSoftDelete(): void
    {
        $tenant = $this->service->create(['name' => 'To Delete']);
        $this->service->delete((int) $tenant['id']);

        $found = $this->service->findById((int) $tenant['id']);
        $this->assertSame(0, (int) $found['is_active']);
    }

    // --- Provision ---

    public function testProvision(): void
    {
        $tenant = $this->service->create(['name' => 'Provisionnable']);
        $result = $this->service->provision((int) $tenant['id']);

        $this->assertSame('provisioned', $result['status']);
        $this->assertGreaterThan(0, $result['migrations_run']);

        // Vérifier que le fichier DB existe
        $refreshed = $this->service->findById((int) $tenant['id']);
        $this->assertSame(1, (int) $refreshed['is_provisioned']);

        $dbPath = $this->tmpDir . '/' . $refreshed['db_path'];
        $this->assertFileExists($dbPath);

        // Vérifier que les tables existent dans la DB tenant
        $tenantConn = new Connection('sqlite:' . $dbPath);
        $tables = $tenantConn->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tableNames = array_column($tables, 'name');

        $this->assertContains('members', $tableNames);
        $this->assertContains('clubs', $tableNames);
        $this->assertContains('_plugin_state', $tableNames);
        $this->assertContains('audit_logs', $tableNames);
    }

    public function testProvisionTwiceThrows(): void
    {
        $tenant = $this->service->create(['name' => 'Once Only']);
        $this->service->provision((int) $tenant['id']);

        $this->expectException(\RuntimeException::class);
        $this->service->provision((int) $tenant['id']);
    }

    // --- Users ---

    public function testAddAndGetUsers(): void
    {
        $tenant = $this->service->create(['name' => 'User Test']);
        $tid = (int) $tenant['id'];

        $this->service->addUser($tid, 1, ['ROLE_ADMIN']);

        $users = $this->service->getUsers($tid);
        $this->assertCount(1, $users);
        $this->assertSame('admin@rlsq-fram.local', $users[0]['email']);
    }

    public function testRemoveUser(): void
    {
        $tenant = $this->service->create(['name' => 'Remove Test']);
        $tid = (int) $tenant['id'];

        $this->service->addUser($tid, 1);
        $this->service->removeUser($tid, 1);

        $this->assertCount(0, $this->service->getUsers($tid));
    }

    public function testGetTenantsForUser(): void
    {
        $this->service->create(['name' => 'Org A', 'owner_user_id' => 1]);
        $this->service->create(['name' => 'Org B', 'owner_user_id' => 1]);

        $tenants = $this->service->getTenantsForUser(1);
        $this->assertCount(2, $tenants);
    }

    // --- TenantContext ---

    public function testTenantContext(): void
    {
        $ctx = new TenantContext();

        $this->assertFalse($ctx->hasTenant());
        $this->assertNull($ctx->getTenantId());

        $ctx->setTenant(['id' => 5, 'slug' => 'test', 'name' => 'Test', 'settings' => '{"theme":"dark"}', 'is_active' => 1, 'db_driver' => 'sqlite', 'db_path' => 'var/t.db']);

        $this->assertTrue($ctx->hasTenant());
        $this->assertSame(5, $ctx->getTenantId());
        $this->assertSame('test', $ctx->getSlug());
        $this->assertSame('dark', $ctx->getSetting('theme'));
        $this->assertTrue($ctx->isActive());
        $this->assertSame('sqlite', $ctx->getDatabaseConfig()['driver']);
    }

    // --- TenantResolver ---

    public function testResolverBySlug(): void
    {
        $this->service->create(['name' => 'Resolve Me', 'slug' => 'resolve-me']);

        $resolver = new TenantResolver($this->conn);
        $tenant = $resolver->findBySlug('resolve-me');

        $this->assertNotNull($tenant);
        $this->assertSame('Resolve Me', $tenant['name']);
    }

    public function testResolverFindAll(): void
    {
        $this->service->create(['name' => 'A']);
        $this->service->create(['name' => 'B']);

        $resolver = new TenantResolver($this->conn);
        $all = $resolver->findAll();

        $this->assertCount(2, $all);
    }
}
