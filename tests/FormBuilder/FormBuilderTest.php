<?php

declare(strict_types=1);

namespace Tests\FormBuilder;

use App\FormBuilder\FormDefinitionService;
use App\Tenant\Database\TenantBaseMigration;
use App\Tenant\Database\TenantFormsMigration;
use App\Tenant\Database\TenantMetaSchemaMigration;
use App\DatabaseBuilder\SchemaDefinitionService;
use App\DatabaseBuilder\DynamicSchemaManager;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class FormBuilderTest extends TestCase
{
    private Connection $conn;
    private FormDefinitionService $service;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');

        $mgr = new MigrationManager($this->conn);
        $mgr->addMigrations([
            new TenantBaseMigration(),
            new TenantMetaSchemaMigration(),
            new TenantFormsMigration(),
        ]);
        $mgr->migrate();

        $this->service = new FormDefinitionService($this->conn);
    }

    // --- Form CRUD ---

    public function testCreateForm(): void
    {
        $form = $this->service->createForm(['name' => 'Contact', 'description' => 'Formulaire de contact']);

        $this->assertSame('Contact', $form['name']);
        $this->assertSame('contact', $form['slug']);
        $this->assertNotNull($form['id']);
    }

    public function testGetAllForms(): void
    {
        $this->service->createForm(['name' => 'Form A']);
        $this->service->createForm(['name' => 'Form B']);

        $all = $this->service->getAllForms();

        $this->assertCount(2, $all);
    }

    public function testUpdateForm(): void
    {
        $form = $this->service->createForm(['name' => 'Original']);
        $updated = $this->service->updateForm((int) $form['id'], [
            'name' => 'Updated',
            'is_published' => 1,
            'settings' => ['require_auth' => true],
        ]);

        $this->assertSame('Updated', $updated['name']);
        $this->assertSame(1, (int) $updated['is_published']);
        $this->assertTrue($updated['settings']['require_auth']);
    }

    public function testDeleteForm(): void
    {
        $form = $this->service->createForm(['name' => 'To Delete']);
        $this->service->deleteForm((int) $form['id']);

        $this->assertNull($this->service->getForm((int) $form['id']));
    }

    // --- Fields ---

    public function testAddField(): void
    {
        $form = $this->service->createForm(['name' => 'Test']);
        $field = $this->service->addField((int) $form['id'], [
            'name' => 'email', 'type' => 'email', 'label' => 'Adresse email',
            'is_required' => true,
            'validation' => ['email' => true],
            'placeholder' => 'vous@exemple.com',
        ]);

        $this->assertSame('email', $field['name']);
        $this->assertSame('email', $field['type']);
        $this->assertSame(1, (int) $field['is_required']);
        $this->assertTrue($field['validation']['email']);
    }

    public function testAddMultipleFieldTypes(): void
    {
        $form = $this->service->createForm(['name' => 'Multi']);
        $fid = (int) $form['id'];

        $this->service->addField($fid, ['name' => 'name', 'type' => 'text', 'label' => 'Nom']);
        $this->service->addField($fid, ['name' => 'email', 'type' => 'email', 'label' => 'Email']);
        $this->service->addField($fid, ['name' => 'age', 'type' => 'number', 'label' => 'Âge']);
        $this->service->addField($fid, ['name' => 'bio', 'type' => 'textarea', 'label' => 'Bio']);
        $this->service->addField($fid, ['name' => 'agree', 'type' => 'checkbox', 'label' => 'J\'accepte']);
        $this->service->addField($fid, ['name' => 'gender', 'type' => 'select', 'label' => 'Genre', 'options' => ['choices' => ['M' => 'Homme', 'F' => 'Femme']]]);

        $fields = $this->service->getFields($fid);
        $this->assertCount(6, $fields);
    }

    public function testUpdateField(): void
    {
        $form = $this->service->createForm(['name' => 'Test']);
        $field = $this->service->addField((int) $form['id'], ['name' => 'x', 'label' => 'Old']);
        $updated = $this->service->updateField((int) $field['id'], ['label' => 'New', 'is_required' => true, 'width' => 6]);

        $this->assertSame('New', $updated['label']);
        $this->assertSame(1, (int) $updated['is_required']);
        $this->assertSame(6, (int) $updated['width']);
    }

    public function testDeleteField(): void
    {
        $form = $this->service->createForm(['name' => 'Test']);
        $field = $this->service->addField((int) $form['id'], ['name' => 'temp', 'label' => 'Temp']);
        $this->service->deleteField((int) $field['id']);

        $this->assertCount(0, $this->service->getFields((int) $form['id']));
    }

    public function testReorderFields(): void
    {
        $form = $this->service->createForm(['name' => 'Reorder']);
        $fid = (int) $form['id'];

        $a = $this->service->addField($fid, ['name' => 'a', 'label' => 'A', 'sort_order' => 0]);
        $b = $this->service->addField($fid, ['name' => 'b', 'label' => 'B', 'sort_order' => 1]);
        $c = $this->service->addField($fid, ['name' => 'c', 'label' => 'C', 'sort_order' => 2]);

        // Reorder: C, A, B
        $this->service->reorderFields($fid, [$c['id'], $a['id'], $b['id']]);

        $fields = $this->service->getFields($fid);
        $this->assertSame('c', $fields[0]['name']);
        $this->assertSame('a', $fields[1]['name']);
        $this->assertSame('b', $fields[2]['name']);
    }

    // --- Visibility rules ---

    public function testFieldVisibilityRules(): void
    {
        $form = $this->service->createForm(['name' => 'Visibility']);
        $field = $this->service->addField((int) $form['id'], [
            'name' => 'other_reason', 'label' => 'Autre raison',
            'visibility_rules' => ['show_if' => ['field' => 'reason', 'operator' => 'equals', 'value' => 'other']],
        ]);

        $this->assertSame('equals', $field['visibility_rules']['show_if']['operator']);
    }

    // --- Submissions ---

    public function testSubmitForm(): void
    {
        $form = $this->service->createForm(['name' => 'Submit Test']);
        $fid = (int) $form['id'];

        $this->service->addField($fid, ['name' => 'name', 'label' => 'Nom', 'is_required' => true]);
        $this->service->addField($fid, ['name' => 'email', 'label' => 'Email', 'is_required' => true, 'validation' => ['email' => true]]);

        // Publier
        $this->service->updateForm($fid, ['is_published' => 1]);

        $result = $this->service->submit($fid, ['name' => 'Alice', 'email' => 'alice@test.com'], 1, '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('submission_id', $result);
    }

    public function testSubmitValidationErrors(): void
    {
        $form = $this->service->createForm(['name' => 'Validation']);
        $fid = (int) $form['id'];

        $this->service->addField($fid, ['name' => 'name', 'label' => 'Nom', 'is_required' => true]);
        $this->service->addField($fid, ['name' => 'email', 'label' => 'Email', 'validation' => ['email' => true]]);

        $result = $this->service->submit($fid, ['name' => '', 'email' => 'not-an-email']);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testGetSubmissions(): void
    {
        $form = $this->service->createForm(['name' => 'Subs']);
        $fid = (int) $form['id'];

        $this->service->addField($fid, ['name' => 'msg', 'label' => 'Message']);

        $this->service->submit($fid, ['msg' => 'Hello']);
        $this->service->submit($fid, ['msg' => 'World']);

        $subs = $this->service->getSubmissions($fid);

        $this->assertSame(2, $subs['total']);
        $this->assertCount(2, $subs['data']);
        $msgs = array_column(array_column($subs['data'], 'data'), 'msg');
        $this->assertContains('Hello', $msgs);
        $this->assertContains('World', $msgs);
    }

    // --- Render ---

    public function testRenderForm(): void
    {
        $form = $this->service->createForm(['name' => 'Render']);
        $fid = (int) $form['id'];

        $this->service->addField($fid, ['name' => 'visible', 'label' => 'Visible', 'is_visible' => true]);
        $this->service->addField($fid, ['name' => 'hidden', 'label' => 'Hidden', 'is_visible' => false]);

        $rendered = $this->service->renderForm($fid);

        $this->assertSame('Render', $rendered['name']);
        $this->assertCount(1, $rendered['fields']); // Only visible
        $this->assertSame('visible', $rendered['fields'][0]['name']);
    }

    // --- Form linked to table ---

    public function testSubmitLinkedToTable(): void
    {
        // Créer une meta-table
        $schemaDef = new SchemaDefinitionService($this->conn);
        $schemaManager = new DynamicSchemaManager($this->conn, $schemaDef);

        $table = $schemaDef->createTable(['name' => 'contacts']);
        $nameCol = $schemaDef->createColumn((int) $table['id'], ['name' => 'full_name', 'type' => 'string']);
        $emailCol = $schemaDef->createColumn((int) $table['id'], ['name' => 'email', 'type' => 'email']);
        $schemaManager->createPhysicalTable((int) $table['id']);

        // Créer un formulaire lié
        $form = $this->service->createForm(['name' => 'Contact Linked', 'table_id' => (int) $table['id']]);
        $fid = (int) $form['id'];

        $this->service->addField($fid, ['name' => 'full_name', 'label' => 'Nom complet', 'column_id' => (int) $nameCol['id']]);
        $this->service->addField($fid, ['name' => 'email', 'label' => 'Email', 'column_id' => (int) $emailCol['id']]);

        // Soumettre
        $result = $this->service->submit($fid, ['full_name' => 'Alice Dupont', 'email' => 'alice@test.com']);

        $this->assertTrue($result['success']);

        // Vérifier que les données sont aussi dans la table physique
        $row = $this->conn->fetchOne('SELECT * FROM contacts WHERE full_name = :n', ['n' => 'Alice Dupont']);
        $this->assertNotFalse($row);
        $this->assertSame('alice@test.com', $row['email']);
    }

    // --- Submission count in getAllForms ---

    public function testSubmissionCountInList(): void
    {
        $form = $this->service->createForm(['name' => 'CountTest']);
        $fid = (int) $form['id'];

        $this->service->addField($fid, ['name' => 'x', 'label' => 'X']);
        $this->service->submit($fid, ['x' => '1']);
        $this->service->submit($fid, ['x' => '2']);

        $all = $this->service->getAllForms();
        $found = array_filter($all, fn ($f) => $f['name'] === 'CountTest');
        $found = reset($found);

        $this->assertSame(2, $found['submission_count']);
    }
}
