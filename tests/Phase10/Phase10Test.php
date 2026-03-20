<?php

declare(strict_types=1);

namespace Tests\Phase10;

use App\AuditLog\AuditLogger;
use App\Backup\BackupService;
use App\I18n\Translator;
use App\ImportExport\ImportExportService;
use App\Media\MediaService;
use App\Notification\NotificationService;
use App\RateLimit\RateLimiter;
use App\RolePermission\RolePermissionService;
use App\Theme\ThemeService;
use App\Webhook\WebhookService;
use App\Workflow\WorkflowEngine;
use App\Tenant\Database\TenantBaseMigration;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class Phase10Test extends TestCase
{
    private Connection $conn;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/rlsq_p10_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->conn = new Connection('sqlite::memory:');
        $mgr = new MigrationManager($this->conn);
        $mgr->addMigration(new TenantBaseMigration());
        $mgr->migrate();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
        RateLimiter::reset();
    }

    private function removeDir(string $d): void
    {
        if (!is_dir($d)) { return; }
        foreach (scandir($d) as $i) { if ($i === '.' || $i === '..') { continue; } $p = $d . DIRECTORY_SEPARATOR . $i; is_dir($p) ? $this->removeDir($p) : unlink($p); }
        rmdir($d);
    }

    // ==================== Audit Logs ====================

    public function testAuditLog(): void
    {
        $logger = new AuditLogger($this->conn);
        $logger->log('create', 1, 'member', 5, ['name' => ['old' => null, 'new' => 'Alice']], '127.0.0.1');
        $logger->log('update', 1, 'member', 5, ['email' => ['old' => 'a@b.com', 'new' => 'c@d.com']]);
        $logger->log('delete', 2, 'club', 3);

        $result = $this->conn->fetchAll('SELECT * FROM audit_logs');
        $this->assertCount(3, $result);
    }

    public function testAuditLogQuery(): void
    {
        $logger = new AuditLogger($this->conn);
        $logger->log('create', 1, 'member', 1);
        $logger->log('update', 1, 'member', 1);
        $logger->log('create', 2, 'club', 1);

        $result = $logger->query(['action' => 'create']);
        $this->assertSame(2, $result['total']);

        $result = $logger->query(['user_id' => 1]);
        $this->assertSame(2, $result['total']);
    }

    // ==================== Webhooks ====================

    public function testWebhookCrud(): void
    {
        $ws = new WebhookService($this->conn);
        $ep = $ws->register('https://example.com/hook', ['member.created', 'payment.completed']);

        $this->assertSame('https://example.com/hook', $ep['url']);
        $this->assertContains('member.created', $ep['events']);
        $this->assertNotEmpty($ep['secret']);

        $all = $ws->getAll();
        $this->assertCount(1, $all);

        $ws->delete((int) $ep['id']);
        $this->assertCount(0, $ws->getAll());
    }

    // ==================== Media ====================

    public function testMediaUpload(): void
    {
        $ms = new MediaService($this->conn, $this->tmpDir . '/uploads');

        // Créer un fichier temporaire
        $tmpFile = $this->tmpDir . '/test.txt';
        file_put_contents($tmpFile, 'hello');

        $file = $ms->upload(['name' => 'test.txt', 'tmp_name' => $tmpFile, 'type' => 'text/plain', 'size' => 5]);

        $this->assertSame('test.txt', $file['original_name']);
        $this->assertSame('text/plain', $file['mime_type']);
    }

    public function testMediaFolders(): void
    {
        $ms = new MediaService($this->conn, $this->tmpDir . '/uploads');

        $folder = $ms->createFolder('Images');
        $this->assertSame('Images', $folder['name']);

        $folders = $ms->listFolders();
        $this->assertCount(1, $folders);
    }

    // ==================== i18n ====================

    public function testTranslator(): void
    {
        $t = new Translator($this->conn, 'fr');

        $t->setTranslation('fr', 'common', 'hello', 'Bonjour');
        $t->setTranslation('en', 'common', 'hello', 'Hello');
        $t->setTranslation('fr', 'common', 'welcome', 'Bienvenue {name}');

        $this->assertSame('Bonjour', $t->trans('common.hello'));
        $this->assertSame('Hello', $t->trans('common.hello', [], 'en'));
        $this->assertSame('Bienvenue Alice', $t->trans('common.welcome', ['name' => 'Alice']));

        $locales = $t->getAvailableLocales();
        $this->assertContains('fr', $locales);
        $this->assertContains('en', $locales);
    }

    // ==================== Theme ====================

    public function testTheme(): void
    {
        $ts = new ThemeService($this->conn);

        $theme = $ts->getTheme();
        $this->assertSame('#ff3e00', $theme['primary_color']);

        $ts->updateTheme(['primary_color' => '#0066ff', 'custom_css' => 'body { margin: 0; }']);
        $theme = $ts->getTheme();
        $this->assertSame('#0066ff', $theme['primary_color']);

        $css = $ts->generateCssVariables();
        $this->assertStringContainsString('--color-primary: #0066ff', $css);
    }

    // ==================== Roles & Permissions ====================

    public function testRolePermission(): void
    {
        $rp = new RolePermissionService($this->conn);

        $role = $rp->createRole('Éditeur', 'editor', ['formations.view', 'formations.edit', 'pages.view', 'pages.edit']);
        $this->assertSame('editor', $role['slug']);
        $this->assertCount(4, $role['permissions']);
        $this->assertTrue($rp->hasPermission($role, 'formations.edit'));
        $this->assertFalse($rp->hasPermission($role, 'members.delete'));

        $rp->updateRole((int) $role['id'], ['permissions' => ['formations.view']]);
        $updated = $rp->getRole((int) $role['id']);
        $this->assertCount(1, $updated['permissions']);

        $perms = $rp->getAvailablePermissions();
        $this->assertGreaterThan(20, count($perms));
    }

    // ==================== Workflows ====================

    public function testWorkflow(): void
    {
        $we = new WorkflowEngine($this->conn);

        $wf = $we->createWorkflow([
            'name' => 'Inscription Formation',
            'trigger_type' => 'on_form_submit',
        ]);
        $this->assertSame('Inscription Formation', $wf['name']);

        $we->addStep((int) $wf['id'], ['type' => 'condition', 'config' => ['field' => 'email', 'operator' => 'is_not_empty'], 'position' => 0]);
        $we->addStep((int) $wf['id'], ['type' => 'action', 'config' => ['action' => 'send_email', 'to' => 'admin@test.com'], 'position' => 1]);

        $full = $we->getWorkflow((int) $wf['id']);
        $this->assertCount(2, $full['steps']);

        $result = $we->execute((int) $wf['id'], ['email' => 'alice@test.com']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame('passed', $result['steps'][0]['status']);
        $this->assertSame('executed', $result['steps'][1]['status']);
    }

    public function testWorkflowConditionFails(): void
    {
        $we = new WorkflowEngine($this->conn);
        $wf = $we->createWorkflow(['name' => 'Test']);
        $we->addStep((int) $wf['id'], ['type' => 'condition', 'config' => ['field' => 'email', 'operator' => 'is_not_empty']]);
        $we->addStep((int) $wf['id'], ['type' => 'action', 'config' => ['action' => 'send_email']]);

        $result = $we->execute((int) $wf['id'], ['email' => '']); // email vide → condition échoue

        $this->assertSame('completed', $result['status']);
        $this->assertSame('failed', $result['steps'][0]['status']);
        $this->assertCount(1, $result['steps']); // Action non exécutée
    }

    // ==================== Notifications ====================

    public function testNotifications(): void
    {
        $ns = new NotificationService($this->conn);

        $ns->create(1, 'info', 'Bienvenue', 'Bienvenue sur la plateforme');
        $ns->create(1, 'alert', 'Paiement reçu', 'Paiement de 50$', ['amount' => 50]);
        $ns->create(2, 'info', 'Autre user');

        $this->assertSame(2, $ns->getUnreadCount(1));

        $notifs = $ns->getForUser(1);
        $this->assertCount(2, $notifs);
        // Vérifier qu'une des notifications a le montant
        $amounts = array_filter(array_column($notifs, 'data'), fn ($d) => isset($d['amount']));
        $this->assertNotEmpty($amounts);

        $ns->markAsRead((int) $notifs[0]['id']);
        $this->assertSame(1, $ns->getUnreadCount(1));

        $ns->markAllAsRead(1);
        $this->assertSame(0, $ns->getUnreadCount(1));
    }

    // ==================== Rate Limiter ====================

    public function testRateLimiter(): void
    {
        $limiter = new RateLimiter(maxRequests: 3, windowSeconds: 60);

        // Simuler des requêtes
        $request = \RLSQ\HttpFoundation\Request::create('/test');

        for ($i = 0; $i < 3; $i++) {
            $event = new \RLSQ\HttpKernel\Event\RequestEvent($request);
            $limiter->onKernelRequest($event);
            $this->assertFalse($event->hasResponse()); // Pas encore limité
        }

        // 4ème requête → limité
        $event = new \RLSQ\HttpKernel\Event\RequestEvent($request);
        $limiter->onKernelRequest($event);
        $this->assertTrue($event->hasResponse());
        $this->assertSame(429, $event->getResponse()->getStatusCode());
    }

    // ==================== Import/Export ====================

    public function testExportCsv(): void
    {
        $this->conn->execute("INSERT INTO members (email, first_name, last_name) VALUES ('a@b.com', 'Alice', 'A')");
        $this->conn->execute("INSERT INTO members (email, first_name, last_name) VALUES ('b@c.com', 'Bob', 'B')");

        $ie = new ImportExportService($this->conn);
        $csv = $ie->exportCsv('members');

        $this->assertStringContainsString('email', $csv);
        $this->assertStringContainsString('a@b.com', $csv);
        $this->assertStringContainsString('Bob', $csv);
    }

    public function testImportCsv(): void
    {
        $ie = new ImportExportService($this->conn);

        $csv = "email,first_name,last_name\nalice@test.com,Alice,Dupont\nbob@test.com,Bob,Martin\n";
        $result = $ie->importCsv('members', $csv);

        $this->assertSame(2, $result['imported']);
        $this->assertEmpty($result['errors']);

        $count = (int) $this->conn->fetchColumn('SELECT COUNT(*) FROM members');
        $this->assertSame(2, $count);
    }

    public function testExportJson(): void
    {
        $this->conn->execute("INSERT INTO clubs (name, slug) VALUES ('Club A', 'club-a')");

        $ie = new ImportExportService($this->conn);
        $json = $ie->exportJson('clubs');

        $data = json_decode($json, true);
        $this->assertCount(1, $data);
        $this->assertSame('Club A', $data[0]['name']);
    }

    // ==================== Backup ====================

    public function testBackupAndRestore(): void
    {
        $this->conn->execute("INSERT INTO members (email, first_name) VALUES ('a@b.com', 'Alice')");
        $this->conn->execute("INSERT INTO clubs (name, slug) VALUES ('Club X', 'club-x')");

        $bs = new BackupService($this->tmpDir . '/backups');

        // Backup
        $backup = $bs->backup($this->conn, 'test-tenant');
        $this->assertArrayHasKey('schema_file', $backup);
        $this->assertGreaterThan(0, $backup['size']);

        // List
        $list = $bs->listBackups('test-tenant');
        $this->assertCount(1, $list);

        // Modifier les données
        $this->conn->execute('DELETE FROM members');
        $this->assertSame(0, (int) $this->conn->fetchColumn('SELECT COUNT(*) FROM members'));

        // Restore
        $result = $bs->restore($this->conn, 'test-tenant', $list[0]['name']);
        $this->assertSame('restored', $result['status']);
        $this->assertGreaterThan(0, $result['rows_restored']);

        // Vérifier
        $this->assertSame(1, (int) $this->conn->fetchColumn('SELECT COUNT(*) FROM members'));
    }
}
