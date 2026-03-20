<?php

declare(strict_types=1);

namespace Tests\Versioning;

use App\Deployment\StandaloneGenerator;
use App\Migration\M001_CreateUsersTable;
use App\Migration\M002_SeedSuperAdmin;
use App\Migration\M003_CreateTenantsTable;
use App\Migration\M004_CreateVersionsTables;
use App\Tenant\Database\TenantBaseMigration;
use App\Tenant\Database\TenantDashboardMigration;
use App\Tenant\Database\TenantFormsMigration;
use App\Tenant\Database\TenantMetaSchemaMigration;
use App\Tenant\Database\TenantPagesMigration;
use App\Versioning\SnapshotService;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class VersioningTest extends TestCase
{
    private Connection $platformConn;
    private Connection $tenantConn;
    private SnapshotService $snapshotService;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/rlsq_version_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        // Platform DB
        $this->platformConn = new Connection('sqlite::memory:');
        $mgr = new MigrationManager($this->platformConn);
        $mgr->addMigrations([new M001_CreateUsersTable(), new M002_SeedSuperAdmin(), new M003_CreateTenantsTable(), new M004_CreateVersionsTables()]);
        $mgr->migrate();

        // Créer un tenant
        $this->platformConn->execute(
            'INSERT INTO tenants (slug, name, db_driver, db_path) VALUES (:s, :n, :d, :p)',
            ['s' => 'test-org', 'n' => 'Test Org', 'd' => 'sqlite', 'p' => ':memory:'],
        );

        // Tenant DB
        $this->tenantConn = new Connection('sqlite::memory:');
        $tmgr = new MigrationManager($this->tenantConn);
        $tmgr->addMigrations([
            new TenantBaseMigration(), new TenantMetaSchemaMigration(),
            new TenantDashboardMigration(), new TenantFormsMigration(), new TenantPagesMigration(),
        ]);
        $tmgr->migrate();

        // Seed some data
        $this->tenantConn->execute("INSERT INTO members (email, first_name) VALUES ('a@b.com', 'Alice')");
        $this->tenantConn->execute("INSERT INTO clubs (name, slug) VALUES ('Club A', 'club-a')");
        $this->tenantConn->execute("INSERT INTO _meta_tables (name, display_name, slug) VALUES ('articles', 'Articles', 'articles')");
        $this->tenantConn->execute("INSERT INTO form_definitions (name, slug) VALUES ('Contact', 'contact')");
        $this->tenantConn->execute("INSERT INTO pages (name, slug, route_path) VALUES ('Home', 'home', '/home')");

        $this->snapshotService = new SnapshotService($this->platformConn);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // --- Snapshot ---

    public function testCaptureSnapshot(): void
    {
        $version = $this->snapshotService->capture($this->tenantConn, 1, 'v1.0.0', 'Initial release');

        $this->assertSame('v1.0.0', $version['version_tag']);
        $this->assertSame('published', $version['status']);

        $snapshot = json_decode($version['snapshot_data'], true);
        $this->assertNotEmpty($snapshot['meta_tables']);
        $this->assertNotEmpty($snapshot['forms']);
        $this->assertNotEmpty($snapshot['pages']);
        $this->assertNotEmpty($snapshot['dashboards']);
        // plugins peut être vide si aucun plugin installé
        $this->assertArrayHasKey('plugins', $snapshot);
    }

    public function testDuplicateTagThrows(): void
    {
        $this->snapshotService->capture($this->tenantConn, 1, 'v1.0.0');

        $this->expectException(\RuntimeException::class);
        $this->snapshotService->capture($this->tenantConn, 1, 'v1.0.0');
    }

    public function testGetVersionsForTenant(): void
    {
        $this->snapshotService->capture($this->tenantConn, 1, 'v1.0.0');
        $this->snapshotService->capture($this->tenantConn, 1, 'v1.1.0', 'Second');

        $versions = $this->snapshotService->getVersionsForTenant(1);

        $this->assertCount(2, $versions);
        $tags = array_column($versions, 'version_tag');
        $this->assertContains('v1.0.0', $tags);
        $this->assertContains('v1.1.0', $tags);
        $this->assertArrayHasKey('summary', $versions[0]);
        $this->assertGreaterThan(0, $versions[0]['summary']['tables']);
    }

    public function testRestoreSnapshot(): void
    {
        // Capture v1
        $v1 = $this->snapshotService->capture($this->tenantConn, 1, 'v1.0.0');

        // Modifier des données
        $this->tenantConn->execute("INSERT INTO members (email, first_name) VALUES ('new@b.com', 'New')");
        $this->tenantConn->execute("DELETE FROM _meta_tables WHERE name = 'articles'");

        // Vérifier que les données ont changé
        $count = (int) $this->tenantConn->fetchColumn('SELECT COUNT(*) FROM members');
        $this->assertSame(2, $count);

        // Restaurer v1
        $result = $this->snapshotService->restore($this->tenantConn, (int) $v1['id']);

        $this->assertSame('restored', $result['status']);

        // Les meta_tables devraient être restaurées
        $tables = $this->tenantConn->fetchAll('SELECT * FROM _meta_tables');
        $this->assertCount(1, $tables);
        $this->assertSame('articles', $tables[0]['name']);
    }

    public function testDiff(): void
    {
        $v1 = $this->snapshotService->capture($this->tenantConn, 1, 'v1.0.0');

        // Ajouter une table
        $this->tenantConn->execute("INSERT INTO _meta_tables (name, display_name, slug) VALUES ('products', 'Products', 'products')");

        $v2 = $this->snapshotService->capture($this->tenantConn, 1, 'v2.0.0');

        $diff = $this->snapshotService->diff((int) $v1['id'], (int) $v2['id']);

        $this->assertSame('v1.0.0', $diff['from']);
        $this->assertSame('v2.0.0', $diff['to']);
        $this->assertNotEmpty($diff['changes']);
    }

    public function testDeleteVersion(): void
    {
        $v = $this->snapshotService->capture($this->tenantConn, 1, 'v-delete');
        $this->snapshotService->deleteVersion((int) $v['id']);

        $this->assertNull($this->snapshotService->getVersion((int) $v['id']));
    }

    // --- Standalone Generator ---

    public function testGenerateStandalone(): void
    {
        $outputDir = $this->tmpDir . '/standalone';
        $generator = new StandaloneGenerator($this->tmpDir);

        // Ajouter une page pour tester l'export Svelte
        $this->tenantConn->execute(
            "INSERT INTO page_components (page_id, type, content, width) VALUES (1, 'heading', 'Test Title', 12)",
        );

        $result = $generator->generate($this->tenantConn, ['name' => 'Test Org', 'slug' => 'test-org'], $outputDir);

        $this->assertGreaterThanOrEqual(5, $result['files_count']);

        // Vérifier les fichiers
        $this->assertFileExists($outputDir . '/composer.json');
        $this->assertFileExists($outputDir . '/.env');
        $this->assertFileExists($outputDir . '/public/index.php');
        $this->assertFileExists($outputDir . '/var/schema.sql');
        $this->assertFileExists($outputDir . '/var/data.json');
        $this->assertFileExists($outputDir . '/config/tenant.json');
        $this->assertFileExists($outputDir . '/README.md');

        // Vérifier le contenu
        $composerJson = json_decode(file_get_contents($outputDir . '/composer.json'), true);
        $this->assertSame('rlsq/test-org', $composerJson['name']);

        $tenantConfig = json_decode(file_get_contents($outputDir . '/config/tenant.json'), true);
        $this->assertSame('Test Org', $tenantConfig['name']);
        $this->assertSame(1, $tenantConfig['pages']);

        // Svelte file devrait exister
        $this->assertFileExists($outputDir . '/frontend/src/routes/Home.svelte');
    }
}
