<?php

declare(strict_types=1);

namespace Tests\DatabaseBuilder;

use App\DatabaseBuilder\DynamicQueryService;
use App\DatabaseBuilder\DynamicSchemaManager;
use App\DatabaseBuilder\SchemaDefinitionService;
use App\DatabaseBuilder\ValidationException;
use App\Tenant\Database\TenantBaseMigration;
use App\Tenant\Database\TenantMetaSchemaMigration;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class DatabaseBuilderTest extends TestCase
{
    private Connection $conn;
    private SchemaDefinitionService $schemaDef;
    private DynamicSchemaManager $schemaManager;
    private DynamicQueryService $queryService;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');

        $mgr = new MigrationManager($this->conn);
        $mgr->addMigrations([new TenantBaseMigration(), new TenantMetaSchemaMigration()]);
        $mgr->migrate();

        $this->schemaDef = new SchemaDefinitionService($this->conn);
        $this->schemaManager = new DynamicSchemaManager($this->conn, $this->schemaDef);
        $this->queryService = new DynamicQueryService($this->conn, $this->schemaDef);
    }

    // ==================== Schema Definition ====================

    public function testCreateTable(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'articles', 'display_name' => 'Articles']);

        $this->assertSame('articles', $table['name']);
        $this->assertSame('Articles', $table['display_name']);

        // Auto-created columns: id, created_at, updated_at
        $colNames = array_column($table['columns'], 'name');
        $this->assertContains('id', $colNames);
        $this->assertContains('created_at', $colNames);
        $this->assertContains('updated_at', $colNames);
    }

    public function testCreateTableWithColumns(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'products']);
        $tableId = (int) $table['id'];

        $this->schemaDef->createColumn($tableId, [
            'name' => 'title', 'display_name' => 'Titre', 'type' => 'string', 'length' => 200,
            'validation_rules' => ['required' => true, 'max_length' => 200],
        ]);
        $this->schemaDef->createColumn($tableId, [
            'name' => 'price', 'display_name' => 'Prix', 'type' => 'float',
        ]);
        $this->schemaDef->createColumn($tableId, [
            'name' => 'email', 'display_name' => 'Email', 'type' => 'email',
            'is_unique' => true,
        ]);

        $full = $this->schemaDef->getTable($tableId);

        $this->assertCount(6, $full['columns']); // id + 3 custom + created_at + updated_at
    }

    public function testGetAllTables(): void
    {
        $this->schemaDef->createTable(['name' => 'table_a']);
        $this->schemaDef->createTable(['name' => 'table_b']);

        $all = $this->schemaDef->getAllTables();

        $this->assertCount(2, $all);
    }

    public function testUpdateTable(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'test']);
        $updated = $this->schemaDef->updateTable((int) $table['id'], ['display_name' => 'Updated', 'icon' => 'star']);

        $this->assertSame('Updated', $updated['display_name']);
        $this->assertSame('star', $updated['icon']);
    }

    public function testDeleteTable(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'to_delete']);
        $this->schemaDef->deleteTable((int) $table['id']);

        $this->assertNull($this->schemaDef->getTable((int) $table['id']));
    }

    public function testUpdateColumn(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'col_test']);
        $col = $this->schemaDef->createColumn((int) $table['id'], ['name' => 'status', 'type' => 'string']);

        $updated = $this->schemaDef->updateColumn((int) $col['id'], ['display_name' => 'Statut', 'is_nullable' => true]);

        $this->assertSame('Statut', $updated['display_name']);
        $this->assertSame(1, (int) $updated['is_nullable']);
    }

    public function testDeleteSystemColumnThrows(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'sys_test']);
        $cols = $this->schemaDef->getColumns((int) $table['id']);
        $idCol = array_filter($cols, fn ($c) => $c['name'] === 'id');
        $idCol = reset($idCol);

        $this->expectException(\RuntimeException::class);
        $this->schemaDef->deleteColumn((int) $idCol['id']);
    }

    public function testCreateRelation(): void
    {
        $t1 = $this->schemaDef->createTable(['name' => 'categories']);
        $t2 = $this->schemaDef->createTable(['name' => 'posts']);

        $rel = $this->schemaDef->createRelation([
            'source_table_id' => (int) $t1['id'],
            'target_table_id' => (int) $t2['id'],
            'type' => 'one_to_many',
            'on_delete' => 'cascade',
        ]);

        $this->assertSame('one_to_many', $rel['type']);
    }

    // ==================== Physical Schema ====================

    public function testCreatePhysicalTable(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'physical_test']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'title', 'type' => 'string']);

        $this->schemaManager->createPhysicalTable((int) $table['id']);

        $this->assertTrue($this->schemaManager->tableExists('physical_test'));
    }

    public function testAddPhysicalColumn(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'alter_test']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        $col = $this->schemaDef->createColumn((int) $table['id'], ['name' => 'description', 'type' => 'text']);
        $this->schemaManager->addPhysicalColumn('alter_test', $col);

        // Vérifier en insérant des données
        $this->conn->execute('INSERT INTO alter_test (description) VALUES (:d)', ['d' => 'hello']);
        $row = $this->conn->fetchOne('SELECT description FROM alter_test WHERE id = 1');
        $this->assertSame('hello', $row['description']);
    }

    public function testSyncAll(): void
    {
        $this->schemaDef->createTable(['name' => 'sync_a']);
        $this->schemaDef->createTable(['name' => 'sync_b']);

        $results = $this->schemaManager->syncAll();

        $this->assertCount(2, $results);
        $this->assertTrue($this->schemaManager->tableExists('sync_a'));
        $this->assertTrue($this->schemaManager->tableExists('sync_b'));
    }

    public function testDropPhysicalTable(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'drop_me']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);
        $this->schemaManager->dropPhysicalTable('drop_me');

        $this->assertFalse($this->schemaManager->tableExists('drop_me'));
    }

    public function testCreatePivotTable(): void
    {
        $this->schemaManager->createPivotTable('tag_post', 'tags', 'posts');

        $this->assertTrue($this->schemaManager->tableExists('tag_post'));
    }

    // ==================== Dynamic CRUD ====================

    public function testCrudOperations(): void
    {
        // Setup
        $table = $this->schemaDef->createTable(['name' => 'crud_items']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'name', 'type' => 'string']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'price', 'type' => 'float']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        // Create
        $item = $this->queryService->create('crud_items', ['name' => 'Widget', 'price' => '19.99']);
        $this->assertSame('Widget', $item['name']);
        $this->assertNotNull($item['id']);

        // Read
        $found = $this->queryService->find('crud_items', (int) $item['id']);
        $this->assertSame('Widget', $found['name']);

        // Update
        $updated = $this->queryService->update('crud_items', (int) $item['id'], ['name' => 'Super Widget']);
        $this->assertSame('Super Widget', $updated['name']);

        // Delete
        $deleted = $this->queryService->delete('crud_items', (int) $item['id']);
        $this->assertTrue($deleted);
        $this->assertNull($this->queryService->find('crud_items', (int) $item['id']));
    }

    public function testListWithPagination(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'paginated']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'title', 'type' => 'string']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        for ($i = 1; $i <= 15; $i++) {
            $this->queryService->create('paginated', ['title' => "Item {$i}"]);
        }

        $page1 = $this->queryService->findAll('paginated', ['page' => 1, 'per_page' => 5]);
        $this->assertCount(5, $page1['data']);
        $this->assertSame(15, $page1['meta']['total']);
        $this->assertSame(3, $page1['meta']['last_page']);

        $page3 = $this->queryService->findAll('paginated', ['page' => 3, 'per_page' => 5]);
        $this->assertCount(5, $page3['data']);
    }

    public function testListWithFilter(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'filterable']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'status', 'type' => 'string']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'score', 'type' => 'integer']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        $this->queryService->create('filterable', ['status' => 'active', 'score' => '90']);
        $this->queryService->create('filterable', ['status' => 'active', 'score' => '50']);
        $this->queryService->create('filterable', ['status' => 'inactive', 'score' => '30']);

        $active = $this->queryService->findAll('filterable', ['filter' => ['status' => 'active']]);
        $this->assertSame(2, $active['meta']['total']);

        $high = $this->queryService->findAll('filterable', ['filter' => ['score' => ['gte' => '60']]]);
        $this->assertSame(1, $high['meta']['total']);
    }

    public function testListWithSearch(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'searchable']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'name', 'type' => 'string']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'desc', 'type' => 'text']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        $this->queryService->create('searchable', ['name' => 'PHP Framework', 'desc' => 'Web dev']);
        $this->queryService->create('searchable', ['name' => 'Python ML', 'desc' => 'Machine learning']);
        $this->queryService->create('searchable', ['name' => 'Go Backend', 'desc' => 'Fast web server']);

        $results = $this->queryService->findAll('searchable', ['search' => 'web']);
        $this->assertSame(2, $results['meta']['total']);
    }

    public function testListWithSort(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'sortable']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'name', 'type' => 'string']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        $this->queryService->create('sortable', ['name' => 'C']);
        $this->queryService->create('sortable', ['name' => 'A']);
        $this->queryService->create('sortable', ['name' => 'B']);

        $asc = $this->queryService->findAll('sortable', ['sort' => 'name']);
        $this->assertSame('A', $asc['data'][0]['name']);

        $desc = $this->queryService->findAll('sortable', ['sort' => '-name']);
        $this->assertSame('C', $desc['data'][0]['name']);
    }

    public function testValidation(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'validated']);
        $this->schemaDef->createColumn((int) $table['id'], [
            'name' => 'email', 'type' => 'email',
            'validation_rules' => ['required' => true, 'email' => true],
        ]);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        $this->expectException(ValidationException::class);
        $this->queryService->create('validated', ['email' => 'not-an-email']);
    }

    public function testExport(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'exportable']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'val', 'type' => 'string']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        $this->queryService->create('exportable', ['val' => 'A']);
        $this->queryService->create('exportable', ['val' => 'B']);

        $rows = $this->queryService->export('exportable');
        $this->assertCount(2, $rows);
    }

    public function testCount(): void
    {
        $table = $this->schemaDef->createTable(['name' => 'countable']);
        $this->schemaDef->createColumn((int) $table['id'], ['name' => 'x', 'type' => 'string']);
        $this->schemaManager->createPhysicalTable((int) $table['id']);

        $this->queryService->create('countable', ['x' => 'a']);
        $this->queryService->create('countable', ['x' => 'b']);

        $this->assertSame(2, $this->queryService->count('countable'));
        $this->assertSame(1, $this->queryService->count('countable', ['x' => 'a']));
    }
}
