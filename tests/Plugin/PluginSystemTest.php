<?php

declare(strict_types=1);

namespace Tests\Plugin;

use App\Plugin\ActivityPlugin\ActivityPlugin;
use App\Plugin\CalendarPlugin\CalendarPlugin;
use App\Plugin\FormationPlugin\FormationPlugin;
use App\Plugin\RoomBookingPlugin\RoomBookingPlugin;
use App\Tenant\Database\TenantBaseMigration;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;
use RLSQ\Plugin\PluginManager;
use RLSQ\Plugin\PluginRegistry;

class PluginSystemTest extends TestCase
{
    private Connection $conn;
    private PluginRegistry $registry;
    private PluginManager $manager;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');

        // Setup tenant DB with base tables
        $mgr = new MigrationManager($this->conn);
        $mgr->addMigration(new TenantBaseMigration());
        $mgr->migrate();

        // Register all core plugins
        $this->registry = new PluginRegistry();
        $this->registry->register(new FormationPlugin());
        $this->registry->register(new ActivityPlugin());
        $this->registry->register(new CalendarPlugin());
        $this->registry->register(new RoomBookingPlugin());

        $this->manager = new PluginManager($this->registry);
    }

    // --- Registry ---

    public function testRegistryContainsAllPlugins(): void
    {
        $this->assertCount(4, $this->registry->all());
        $this->assertTrue($this->registry->has('formations'));
        $this->assertTrue($this->registry->has('activities'));
        $this->assertTrue($this->registry->has('calendar'));
        $this->assertTrue($this->registry->has('room-booking'));
    }

    public function testRegistryGetPlugin(): void
    {
        $plugin = $this->registry->get('formations');

        $this->assertSame('Formations', $plugin->getName());
        $this->assertSame('formations', $plugin->getSlug());
        $this->assertSame('1.0.0', $plugin->getVersion());
        $this->assertNotEmpty($plugin->getDescription());
    }

    public function testRegistryToArray(): void
    {
        $arr = $this->registry->toArray();

        $this->assertCount(4, $arr);
        $this->assertSame('formations', $arr[0]['slug']);
    }

    // --- Install ---

    public function testInstallPlugin(): void
    {
        $state = $this->manager->install('formations', $this->conn);

        $this->assertSame('formations', $state['plugin_slug']);
        $this->assertSame('1.0.0', $state['version']);
        $this->assertSame(1, (int) $state['is_active']);

        // Les tables ont été créées
        $tables = $this->conn->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'formation%'");
        $tableNames = array_column($tables, 'name');
        $this->assertContains('formations', $tableNames);
        $this->assertContains('formation_registrations', $tableNames);
    }

    public function testInstallWithSettings(): void
    {
        $settings = ['require_payment' => true, 'max_registrations_per_member' => 3];
        $state = $this->manager->install('formations', $this->conn, $settings);

        $decoded = json_decode($state['settings'], true);
        $this->assertTrue($decoded['require_payment']);
        $this->assertSame(3, $decoded['max_registrations_per_member']);
    }

    public function testInstallTwiceThrows(): void
    {
        $this->manager->install('formations', $this->conn);

        $this->expectException(\RuntimeException::class);
        $this->manager->install('formations', $this->conn);
    }

    public function testInstallNonexistentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->manager->install('nonexistent', $this->conn);
    }

    // --- Uninstall ---

    public function testUninstallPlugin(): void
    {
        $this->manager->install('formations', $this->conn);
        $this->manager->uninstall('formations', $this->conn);

        $this->assertFalse($this->manager->isInstalled('formations', $this->conn));

        // Tables dropped
        $tables = $this->conn->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name = 'formations'");
        $this->assertEmpty($tables);
    }

    // --- Activate / Deactivate ---

    public function testDeactivateAndActivate(): void
    {
        $this->manager->install('calendar', $this->conn);

        $this->manager->deactivate('calendar', $this->conn);
        $this->assertFalse($this->manager->isActive('calendar', $this->conn));
        $this->assertTrue($this->manager->isInstalled('calendar', $this->conn));

        $this->manager->activate('calendar', $this->conn);
        $this->assertTrue($this->manager->isActive('calendar', $this->conn));
    }

    // --- Settings ---

    public function testUpdateSettings(): void
    {
        $this->manager->install('room-booking', $this->conn);
        $this->manager->updateSettings('room-booking', $this->conn, ['require_approval' => false]);

        $state = $this->manager->getState('room-booking', $this->conn);
        $settings = json_decode($state['settings'], true);
        $this->assertFalse($settings['require_approval']);
    }

    // --- Status ---

    public function testGetPluginsWithStatus(): void
    {
        $this->manager->install('formations', $this->conn);
        $this->manager->install('calendar', $this->conn);
        $this->manager->deactivate('calendar', $this->conn);

        $plugins = $this->manager->getPluginsWithStatus($this->conn);

        $this->assertCount(4, $plugins);

        $formations = $this->findBySlug($plugins, 'formations');
        $this->assertTrue($formations['is_installed']);
        $this->assertTrue($formations['is_active']);

        $calendar = $this->findBySlug($plugins, 'calendar');
        $this->assertTrue($calendar['is_installed']);
        $this->assertFalse($calendar['is_active']);

        $activities = $this->findBySlug($plugins, 'activities');
        $this->assertFalse($activities['is_installed']);
        $this->assertFalse($activities['is_active']);
    }

    // --- Active plugins ---

    public function testGetActivePlugins(): void
    {
        $this->manager->install('formations', $this->conn);
        $this->manager->install('calendar', $this->conn);
        $this->manager->install('activities', $this->conn);
        $this->manager->deactivate('calendar', $this->conn);

        $active = $this->manager->getActivePlugins($this->conn);

        $this->assertCount(2, $active);
        $slugs = array_map(fn ($p) => $p->getSlug(), $active);
        $this->assertContains('formations', $slugs);
        $this->assertContains('activities', $slugs);
        $this->assertNotContains('calendar', $slugs);
    }

    // --- Menu items ---

    public function testPluginMenuItems(): void
    {
        $formation = $this->registry->get('formations');
        $items = $formation->getMenuItems();

        $this->assertCount(2, $items);
        $this->assertSame('Formations', $items[0]['label']);
        $this->assertSame('/formations', $items[0]['path']);
    }

    // --- Settings schema ---

    public function testSettingsSchema(): void
    {
        $formation = $this->registry->get('formations');
        $schema = $formation->getSettingsSchema();

        $this->assertArrayHasKey('fields', $schema);
        $this->assertGreaterThan(0, count($schema['fields']));
        $this->assertSame('require_payment', $schema['fields'][0]['name']);
    }

    // --- All 4 plugins install ---

    public function testInstallAllPlugins(): void
    {
        $this->manager->install('formations', $this->conn);
        $this->manager->install('activities', $this->conn);
        $this->manager->install('calendar', $this->conn);
        $this->manager->install('room-booking', $this->conn);

        $installed = $this->manager->getInstalledPlugins($this->conn);
        $this->assertCount(4, $installed);

        // Vérifier que les tables existent
        $allTables = $this->conn->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
        $names = array_column($allTables, 'name');

        $this->assertContains('formations', $names);
        $this->assertContains('activities', $names);
        $this->assertContains('activity_sessions', $names);
        $this->assertContains('calendar_events', $names);
        $this->assertContains('rooms', $names);
        $this->assertContains('room_bookings', $names);
    }

    private function findBySlug(array $plugins, string $slug): ?array
    {
        foreach ($plugins as $p) {
            if ($p['slug'] === $slug) {
                return $p;
            }
        }

        return null;
    }
}
