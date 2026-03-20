<?php

declare(strict_types=1);

namespace Tests\PageBuilder;

use App\PageBuilder\PageService;
use App\Tenant\Database\TenantBaseMigration;
use App\Tenant\Database\TenantPagesMigration;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class PageBuilderTest extends TestCase
{
    private Connection $conn;
    private PageService $service;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');
        $mgr = new MigrationManager($this->conn);
        $mgr->addMigrations([new TenantBaseMigration(), new TenantPagesMigration()]);
        $mgr->migrate();

        $this->service = new PageService($this->conn);
    }

    // --- Pages CRUD ---

    public function testCreatePage(): void
    {
        $page = $this->service->createPage(['name' => 'Accueil', 'meta_title' => 'Page d\'accueil']);

        $this->assertSame('Accueil', $page['name']);
        $this->assertSame('accueil', $page['slug']);
        $this->assertSame('/accueil', $page['route_path']);
    }

    public function testGetAllPages(): void
    {
        $this->service->createPage(['name' => 'A']);
        $this->service->createPage(['name' => 'B']);

        $this->assertCount(2, $this->service->getAllPages());
    }

    public function testUpdatePage(): void
    {
        $page = $this->service->createPage(['name' => 'Old']);
        $updated = $this->service->updatePage((int) $page['id'], [
            'name' => 'New', 'is_published' => 1, 'meta_description' => 'Desc',
        ]);

        $this->assertSame('New', $updated['name']);
        $this->assertSame(1, (int) $updated['is_published']);
    }

    public function testDeletePage(): void
    {
        $page = $this->service->createPage(['name' => 'Del']);
        $this->service->deletePage((int) $page['id']);

        $this->assertNull($this->service->getPage((int) $page['id']));
    }

    public function testDuplicatePage(): void
    {
        $page = $this->service->createPage(['name' => 'Original']);
        $this->service->addComponent((int) $page['id'], ['type' => 'heading', 'content' => 'Hello']);
        $this->service->addComponent((int) $page['id'], ['type' => 'text', 'content' => 'World']);

        $copy = $this->service->duplicatePage((int) $page['id']);

        $this->assertStringContainsString('copie', $copy['name']);
        $this->assertCount(2, $copy['components']);
    }

    // --- Components ---

    public function testAddComponent(): void
    {
        $page = $this->service->createPage(['name' => 'Test']);
        $comp = $this->service->addComponent((int) $page['id'], [
            'type' => 'heading', 'content' => 'Mon titre',
            'props' => ['level' => 1], 'width' => 12,
        ]);

        $this->assertSame('heading', $comp['type']);
        $this->assertSame('Mon titre', $comp['content']);
        $this->assertSame(1, $comp['props']['level']);
    }

    public function testAddMultipleComponentTypes(): void
    {
        $page = $this->service->createPage(['name' => 'Multi']);
        $pid = (int) $page['id'];

        $this->service->addComponent($pid, ['type' => 'heading', 'content' => 'Titre', 'props' => ['level' => 1]]);
        $this->service->addComponent($pid, ['type' => 'text', 'content' => 'Paragraphe']);
        $this->service->addComponent($pid, ['type' => 'image', 'props' => ['src' => '/img.jpg', 'alt' => 'Photo']]);
        $this->service->addComponent($pid, ['type' => 'button', 'content' => 'Cliquez', 'props' => ['url' => '/page']]);
        $this->service->addComponent($pid, ['type' => 'divider']);
        $this->service->addComponent($pid, ['type' => 'spacer', 'props' => ['height' => 48]]);
        $this->service->addComponent($pid, ['type' => 'card', 'content' => 'Contenu carte']);
        $this->service->addComponent($pid, ['type' => 'html', 'content' => '<div>Custom</div>']);
        $this->service->addComponent($pid, ['type' => 'iframe', 'props' => ['src' => 'https://example.com']]);

        $comps = $this->service->getComponents($pid);

        $this->assertCount(9, $comps);
    }

    public function testUpdateComponent(): void
    {
        $page = $this->service->createPage(['name' => 'Test']);
        $comp = $this->service->addComponent((int) $page['id'], ['type' => 'text', 'content' => 'Old']);
        $updated = $this->service->updateComponent((int) $comp['id'], [
            'content' => 'New', 'styles' => ['backgroundColor' => '#ff0000'], 'width' => 6,
        ]);

        $this->assertSame('New', $updated['content']);
        $this->assertSame('#ff0000', $updated['styles']['backgroundColor']);
        $this->assertSame(6, (int) $updated['width']);
    }

    public function testDeleteComponent(): void
    {
        $page = $this->service->createPage(['name' => 'Test']);
        $comp = $this->service->addComponent((int) $page['id'], ['type' => 'text']);
        $this->service->deleteComponent((int) $comp['id']);

        $this->assertCount(0, $this->service->getComponents((int) $page['id']));
    }

    public function testUpdatePositions(): void
    {
        $page = $this->service->createPage(['name' => 'Pos']);
        $pid = (int) $page['id'];

        $a = $this->service->addComponent($pid, ['type' => 'text', 'content' => 'A', 'sort_order' => 0]);
        $b = $this->service->addComponent($pid, ['type' => 'text', 'content' => 'B', 'sort_order' => 1]);

        $this->service->updateComponentPositions($pid, [
            ['id' => $b['id'], 'position_x' => 0, 'position_y' => 0, 'width' => 6, 'height' => 1, 'sort_order' => 0],
            ['id' => $a['id'], 'position_x' => 6, 'position_y' => 0, 'width' => 6, 'height' => 1, 'sort_order' => 1],
        ]);

        $comps = $this->service->getComponents($pid);
        $this->assertSame('B', $comps[0]['content']);
    }

    // --- Render ---

    public function testRenderPage(): void
    {
        $page = $this->service->createPage(['name' => 'Render', 'meta_title' => 'Test Render']);
        $pid = (int) $page['id'];

        $this->service->addComponent($pid, ['type' => 'heading', 'content' => 'Hello World', 'props' => ['level' => 1]]);
        $this->service->addComponent($pid, ['type' => 'text', 'content' => 'Some text']);
        $this->service->addComponent($pid, ['type' => 'button', 'content' => 'Click me', 'props' => ['url' => '/action']]);

        $html = $this->service->renderPage($pid);

        $this->assertStringContainsString('<title>Test Render</title>', $html);
        $this->assertStringContainsString('<h1>Hello World</h1>', $html);
        $this->assertStringContainsString('Some text', $html);
        $this->assertStringContainsString('Click me', $html);
        $this->assertStringContainsString('/action', $html);
    }

    public function testRenderWithStyles(): void
    {
        $page = $this->service->createPage(['name' => 'Styled']);
        $this->service->addComponent((int) $page['id'], [
            'type' => 'text', 'content' => 'Styled',
            'styles' => ['backgroundColor' => '#ff0000', 'color' => '#ffffff', 'padding' => '20px'],
        ]);

        $html = $this->service->renderPage((int) $page['id']);

        $this->assertStringContainsString('background-color:#ff0000', $html);
        $this->assertStringContainsString('color:#ffffff', $html);
    }

    public function testGenerateSvelteCode(): void
    {
        $page = $this->service->createPage(['name' => 'Svelte Page']);
        $pid = (int) $page['id'];

        $this->service->addComponent($pid, ['type' => 'heading', 'content' => 'Title', 'props' => ['level' => 2]]);
        $this->service->addComponent($pid, ['type' => 'text', 'content' => 'Paragraph']);

        $svelte = $this->service->generateSvelteCode($pid);

        $this->assertStringContainsString('svelte:head', $svelte);
        $this->assertStringContainsString('<h2>Title</h2>', $svelte);
        $this->assertStringContainsString('Paragraph', $svelte);
        $this->assertStringContainsString('page-grid', $svelte);
    }

    public function testRenderNonExistent(): void
    {
        $this->assertNull($this->service->renderPage(999));
    }

    // --- Component count in listing ---

    public function testComponentCountInListing(): void
    {
        $page = $this->service->createPage(['name' => 'Count']);
        $this->service->addComponent((int) $page['id'], ['type' => 'text']);
        $this->service->addComponent((int) $page['id'], ['type' => 'text']);

        $all = $this->service->getAllPages();
        $found = array_filter($all, fn ($p) => $p['name'] === 'Count');
        $found = reset($found);

        $this->assertSame(2, $found['component_count']);
    }
}
