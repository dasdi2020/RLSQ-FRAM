<?php

declare(strict_types=1);

namespace Tests\Dashboard;

use App\Dashboard\DashboardService;
use App\Tenant\Database\TenantBaseMigration;
use App\Tenant\Database\TenantDashboardMigration;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class DashboardServiceTest extends TestCase
{
    private Connection $conn;
    private DashboardService $service;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');

        $mgr = new MigrationManager($this->conn);
        $mgr->addMigrations([new TenantBaseMigration(), new TenantDashboardMigration()]);
        $mgr->migrate();

        // Seed some test data
        $this->conn->execute("INSERT INTO members (email, first_name, last_name) VALUES (:e, :fn, :ln)", ['e' => 'alice@test.com', 'fn' => 'Alice', 'ln' => 'A']);
        $this->conn->execute("INSERT INTO members (email, first_name, last_name) VALUES (:e, :fn, :ln)", ['e' => 'bob@test.com', 'fn' => 'Bob', 'ln' => 'B']);
        $this->conn->execute("INSERT INTO clubs (name, slug) VALUES (:n, :s)", ['n' => 'Club X', 's' => 'club-x']);

        $this->service = new DashboardService($this->conn);
    }

    // --- Default dashboards ---

    public function testDefaultDashboardsCreated(): void
    {
        $all = $this->service->getAllDashboards();

        $this->assertCount(3, $all);
        $types = array_column($all, 'type');
        $this->assertContains('federation', $types);
        $this->assertContains('club', $types);
        $this->assertContains('member', $types);
    }

    public function testGetDefaultForRole(): void
    {
        $fed = $this->service->getDefaultForRole('ROLE_FEDERATION_ADMIN');
        $this->assertNotNull($fed);
        $this->assertSame('federation', $fed['type']);
        $this->assertNotEmpty($fed['widgets']);

        $club = $this->service->getDefaultForRole('ROLE_CLUB_ADMIN');
        $this->assertNotNull($club);
        $this->assertSame('club', $club['type']);

        $member = $this->service->getDefaultForRole('ROLE_MEMBER');
        $this->assertNotNull($member);
        $this->assertSame('member', $member['type']);
    }

    // --- Dashboard CRUD ---

    public function testCreateDashboard(): void
    {
        $d = $this->service->createDashboard([
            'name' => 'Custom Dashboard',
            'type' => 'custom',
            'target_roles' => ['ROLE_CLUB_ADMIN'],
        ]);

        $this->assertSame('Custom Dashboard', $d['name']);
        $this->assertContains('ROLE_CLUB_ADMIN', $d['target_roles']);
    }

    public function testUpdateDashboard(): void
    {
        $d = $this->service->createDashboard(['name' => 'Test']);
        $updated = $this->service->updateDashboard((int) $d['id'], ['name' => 'Updated']);

        $this->assertSame('Updated', $updated['name']);
    }

    public function testDeleteNonDefaultDashboard(): void
    {
        $d = $this->service->createDashboard(['name' => 'Deletable']);
        $this->service->deleteDashboard((int) $d['id']);

        $this->assertNull($this->service->getDashboard((int) $d['id']));
    }

    public function testDeleteDefaultDashboardDoesNothing(): void
    {
        $fed = $this->service->getDefaultForRole('ROLE_FEDERATION_ADMIN');
        $this->service->deleteDashboard((int) $fed['id']); // is_default = 1, should be ignored

        $this->assertNotNull($this->service->getDashboard((int) $fed['id']));
    }

    // --- Widgets ---

    public function testDefaultWidgetsExist(): void
    {
        $fed = $this->service->getDefaultForRole('ROLE_FEDERATION_ADMIN');

        $this->assertGreaterThanOrEqual(4, count($fed['widgets']));

        $types = array_column($fed['widgets'], 'widget_type');
        $this->assertContains('counter', $types);
        $this->assertContains('datatable', $types);
    }

    public function testAddWidget(): void
    {
        $d = $this->service->createDashboard(['name' => 'With Widgets']);
        $w = $this->service->addWidget((int) $d['id'], [
            'widget_type' => 'counter',
            'title' => 'Custom Counter',
            'config' => ['source' => 'members', 'operation' => 'count'],
            'width' => 2,
        ]);

        $this->assertSame('counter', $w['widget_type']);
        $this->assertSame('Custom Counter', $w['title']);
        $this->assertSame('members', $w['config']['source']);
    }

    public function testUpdateWidget(): void
    {
        $d = $this->service->createDashboard(['name' => 'Test']);
        $w = $this->service->addWidget((int) $d['id'], ['widget_type' => 'counter', 'title' => 'Old']);
        $updated = $this->service->updateWidget((int) $w['id'], ['title' => 'New', 'width' => 3]);

        $this->assertSame('New', $updated['title']);
        $this->assertSame(3, (int) $updated['width']);
    }

    public function testDeleteWidget(): void
    {
        $d = $this->service->createDashboard(['name' => 'Test']);
        $w = $this->service->addWidget((int) $d['id'], ['widget_type' => 'counter']);
        $this->service->deleteWidget((int) $w['id']);

        $widgets = $this->service->getWidgets((int) $d['id']);
        $this->assertCount(0, $widgets);
    }

    public function testUpdateWidgetPositions(): void
    {
        $d = $this->service->createDashboard(['name' => 'Test']);
        $w1 = $this->service->addWidget((int) $d['id'], ['widget_type' => 'counter', 'title' => 'A']);
        $w2 = $this->service->addWidget((int) $d['id'], ['widget_type' => 'counter', 'title' => 'B']);

        $this->service->updateWidgetPositions((int) $d['id'], [
            ['id' => $w1['id'], 'position_x' => 2, 'position_y' => 1, 'width' => 2, 'height' => 1, 'sort_order' => 1],
            ['id' => $w2['id'], 'position_x' => 0, 'position_y' => 0, 'width' => 2, 'height' => 1, 'sort_order' => 0],
        ]);

        $widgets = $this->service->getWidgets((int) $d['id']);
        $first = $widgets[0]; // sort_order 0 = w2
        $this->assertSame('B', $first['title']);
        $this->assertSame(0, (int) $first['position_x']);
    }

    // --- Widget Data Resolution ---

    public function testResolveCounterWidget(): void
    {
        $data = $this->service->resolveWidgetData([
            'widget_type' => 'counter',
            'config' => ['source' => 'members', 'operation' => 'count'],
        ]);

        $this->assertSame(2, $data['value']); // 2 members seeded
    }

    public function testResolveCounterWithFilter(): void
    {
        $data = $this->service->resolveWidgetData([
            'widget_type' => 'counter',
            'config' => ['source' => 'clubs', 'operation' => 'count', 'filter' => ['is_active' => 1]],
        ]);

        $this->assertSame(1, $data['value']);
    }

    public function testResolveDataTableWidget(): void
    {
        $data = $this->service->resolveWidgetData([
            'widget_type' => 'datatable',
            'config' => ['source' => 'members', 'limit' => 5, 'columns' => ['first_name', 'email']],
        ]);

        $this->assertCount(2, $data['rows']);
        $this->assertArrayHasKey('first_name', $data['rows'][0]);
    }

    public function testResolveWelcomeWidget(): void
    {
        $data = $this->service->resolveWidgetData([
            'widget_type' => 'welcome',
            'config' => ['message' => 'Hello!'],
        ]);

        $this->assertSame('Hello!', $data['message']);
    }
}
