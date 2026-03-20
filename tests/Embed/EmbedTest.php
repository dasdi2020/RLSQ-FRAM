<?php

declare(strict_types=1);

namespace Tests\Embed;

use App\Embed\EmbedRenderer;
use App\Embed\EmbedService;
use App\Plugin\FormationPlugin\FormationPlugin;
use App\Plugin\ActivityPlugin\ActivityPlugin;
use App\Plugin\CalendarPlugin\CalendarPlugin;
use App\Plugin\RoomBookingPlugin\RoomBookingPlugin;
use App\Tenant\Database\TenantBaseMigration;
use App\Tenant\Database\TenantEmbedMigration;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class EmbedTest extends TestCase
{
    private Connection $conn;
    private EmbedService $service;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');
        $mgr = new MigrationManager($this->conn);
        $mgr->addMigrations([new TenantBaseMigration(), new TenantEmbedMigration()]);
        $mgr->migrate();

        // Install plugin tables
        (new FormationPlugin())->install($this->conn);
        (new ActivityPlugin())->install($this->conn);
        (new CalendarPlugin())->install($this->conn);
        (new RoomBookingPlugin())->install($this->conn);

        $this->service = new EmbedService($this->conn);
    }

    // --- EmbedService CRUD ---

    public function testCreateEmbed(): void
    {
        $embed = $this->service->createEmbed([
            'name' => 'Formations Widget',
            'module_slug' => 'formations',
            'allowed_domains' => ['example.com', '*.test.com'],
        ]);

        $this->assertSame('Formations Widget', $embed['name']);
        $this->assertSame('formations', $embed['module_slug']);
        $this->assertNotEmpty($embed['token']);
        $this->assertSame(64, strlen($embed['token']));
        $this->assertContains('example.com', $embed['allowed_domains']);
    }

    public function testGetAllEmbeds(): void
    {
        $this->service->createEmbed(['name' => 'A', 'module_slug' => 'formations']);
        $this->service->createEmbed(['name' => 'B', 'module_slug' => 'activities']);

        $this->assertCount(2, $this->service->getAllEmbeds());
    }

    public function testUpdateEmbed(): void
    {
        $embed = $this->service->createEmbed(['name' => 'Old', 'module_slug' => 'formations']);
        $updated = $this->service->updateEmbed((int) $embed['id'], [
            'name' => 'New',
            'theme' => ['primary_color' => '#00ff00'],
        ]);

        $this->assertSame('New', $updated['name']);
        $this->assertSame('#00ff00', $updated['theme']['primary_color']);
    }

    public function testDeleteEmbed(): void
    {
        $embed = $this->service->createEmbed(['name' => 'Del', 'module_slug' => 'formations']);
        $this->service->deleteEmbed((int) $embed['id']);

        $this->assertNull($this->service->getEmbed((int) $embed['id']));
    }

    public function testRegenerateToken(): void
    {
        $embed = $this->service->createEmbed(['name' => 'Token', 'module_slug' => 'formations']);
        $oldToken = $embed['token'];

        $updated = $this->service->regenerateToken((int) $embed['id']);

        $this->assertNotSame($oldToken, $updated['token']);
        $this->assertSame(64, strlen($updated['token']));
    }

    public function testGetByToken(): void
    {
        $embed = $this->service->createEmbed(['name' => 'Find', 'module_slug' => 'formations']);

        $found = $this->service->getEmbedByToken($embed['token']);

        $this->assertNotNull($found);
        $this->assertSame('Find', $found['name']);
    }

    public function testInactiveTokenReturnsNull(): void
    {
        $embed = $this->service->createEmbed(['name' => 'Inactive', 'module_slug' => 'formations']);
        $this->service->updateEmbed((int) $embed['id'], ['is_active' => 0]);

        $this->assertNull($this->service->getEmbedByToken($embed['token']));
    }

    // --- Domain validation ---

    public function testDomainAllowedWildcard(): void
    {
        $embed = ['allowed_domains' => ['*']];
        $this->assertTrue($this->service->isDomainAllowed($embed, 'https://anything.com'));
    }

    public function testDomainAllowedExact(): void
    {
        $embed = ['allowed_domains' => ['example.com', 'other.com']];
        $this->assertTrue($this->service->isDomainAllowed($embed, 'https://example.com/page'));
        $this->assertFalse($this->service->isDomainAllowed($embed, 'https://evil.com'));
    }

    public function testDomainAllowedSubdomainWildcard(): void
    {
        $embed = ['allowed_domains' => ['*.example.com']];
        $this->assertTrue($this->service->isDomainAllowed($embed, 'https://app.example.com'));
        $this->assertTrue($this->service->isDomainAllowed($embed, 'https://sub.deep.example.com'));
        $this->assertFalse($this->service->isDomainAllowed($embed, 'https://evil.com'));
    }

    // --- Snippet ---

    public function testGenerateSnippet(): void
    {
        $embed = $this->service->createEmbed(['name' => 'Widget', 'module_slug' => 'formations']);
        $snippet = $this->service->generateSnippet($embed, 'https://app.rlsq.ca');

        $this->assertStringContainsString($embed['token'], $snippet);
        $this->assertStringContainsString('https://app.rlsq.ca/embed/', $snippet);
        $this->assertStringContainsString('iframe', $snippet);
        $this->assertStringContainsString('rlsq-resize', $snippet);
        $this->assertStringContainsString('rlsq-payment-success', $snippet);
    }

    // --- Renderer ---

    public function testRenderFormations(): void
    {
        // Seed formation
        $this->conn->execute(
            "INSERT INTO formations (title, description, price, currency, status, start_date) VALUES (:t, :d, :p, :c, 'published', :sd)",
            ['t' => 'PHP Avancé', 'd' => 'Formation PHP 8', 'p' => 99.99, 'c' => 'CAD', 'sd' => '2026-06-01'],
        );

        $embed = $this->service->createEmbed(['name' => 'F', 'module_slug' => 'formations']);
        $renderer = new EmbedRenderer($this->conn);
        $html = $renderer->render($embed);

        $this->assertStringContainsString('PHP Avancé', $html);
        $this->assertStringContainsString('99,99', $html);
        $this->assertStringContainsString("S'inscrire", $html);
        $this->assertStringContainsString('rlsq-resize', $html);
    }

    public function testRenderActivities(): void
    {
        $this->conn->execute(
            "INSERT INTO activities (title, description, category, status) VALUES ('Yoga', 'Session de yoga', 'Sport', 'published')",
        );

        $embed = $this->service->createEmbed(['name' => 'A', 'module_slug' => 'activities']);
        $html = (new EmbedRenderer($this->conn))->render($embed);

        $this->assertStringContainsString('Yoga', $html);
        $this->assertStringContainsString('Sport', $html);
    }

    public function testRenderCalendar(): void
    {
        $this->conn->execute(
            "INSERT INTO calendar_events (title, start_at, is_public) VALUES ('Réunion', '2026-12-01 10:00:00', 1)",
        );

        $embed = $this->service->createEmbed(['name' => 'C', 'module_slug' => 'calendar']);
        $html = (new EmbedRenderer($this->conn))->render($embed);

        $this->assertStringContainsString('Réunion', $html);
    }

    public function testRenderRoomBooking(): void
    {
        $this->conn->execute(
            "INSERT INTO rooms (name, description, capacity, hourly_rate, is_active) VALUES ('Salle A', 'Grande salle', 50, 75.00, 1)",
        );

        $embed = $this->service->createEmbed(['name' => 'R', 'module_slug' => 'room-booking']);
        $html = (new EmbedRenderer($this->conn))->render($embed);

        $this->assertStringContainsString('Salle A', $html);
        $this->assertStringContainsString('75,00', $html);
        $this->assertStringContainsString('50 pers.', $html);
    }

    public function testRenderEmptyModule(): void
    {
        $embed = $this->service->createEmbed(['name' => 'Empty', 'module_slug' => 'formations']);
        $html = (new EmbedRenderer($this->conn))->render($embed);

        $this->assertStringContainsString('Aucune formation', $html);
    }

    public function testRenderWithTheme(): void
    {
        $embed = $this->service->createEmbed([
            'name' => 'Themed', 'module_slug' => 'formations',
            'theme' => ['primary_color' => '#0066ff', 'background_color' => '#f5f5f5'],
        ]);

        $html = (new EmbedRenderer($this->conn))->render($embed);

        $this->assertStringContainsString('#0066ff', $html);
        $this->assertStringContainsString('#f5f5f5', $html);
    }

    public function testViewsCountIncremented(): void
    {
        $embed = $this->service->createEmbed(['name' => 'Views', 'module_slug' => 'formations']);
        $this->assertSame(0, (int) $embed['views_count']);

        $this->service->getEmbedByToken($embed['token']);
        $this->service->getEmbedByToken($embed['token']);

        $updated = $this->service->getEmbed((int) $embed['id']);
        $this->assertSame(2, (int) $updated['views_count']);
    }
}
