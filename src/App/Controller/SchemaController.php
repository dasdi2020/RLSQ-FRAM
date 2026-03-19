<?php

declare(strict_types=1);

namespace App\Controller;

use App\DatabaseBuilder\DynamicSchemaManager;
use App\DatabaseBuilder\SchemaDefinitionService;
use App\Tenant\TenantContext;
use App\Tenant\Database\TenantConnectionFactory;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/t/{tenantSlug}/schema')]
#[RequireAuth]
class SchemaController extends AbstractController
{
    private function getServices(string $tenantSlug): array
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        if ($tenant === null) {
            throw new \RuntimeException('Tenant introuvable.');
        }

        $factory = $this->get(\App\Tenant\Database\TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);
        $conn = $factory->getConnection($ctx);

        $schemaDef = new SchemaDefinitionService($conn);
        $schemaManager = new DynamicSchemaManager($conn, $schemaDef);

        return [$conn, $schemaDef, $schemaManager];
    }

    // ==================== TABLES ====================

    #[Route('/tables', name: 'schema_tables_list', methods: ['GET'])]
    #[ApiRoute(summary: 'Lister les tables', tags: ['Schema'])]
    public function listTables(string $tenantSlug): JsonResponse
    {
        [, $schemaDef] = $this->getServices($tenantSlug);

        return $this->json(['data' => $schemaDef->getAllTables()]);
    }

    #[Route('/tables', name: 'schema_tables_create', methods: ['POST'])]
    #[ApiRoute(summary: 'Créer une table', tags: ['Schema'], responses: [201 => 'Créée'])]
    public function createTable(string $tenantSlug, Request $request): JsonResponse
    {
        [$conn, $schemaDef, $schemaManager] = $this->getServices($tenantSlug);

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $table = $schemaDef->createTable($data);
            $schemaManager->createPhysicalTable((int) $table['id']);

            return $this->json(['data' => $table, 'message' => 'Table créée.'], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/tables/{id}', name: 'schema_tables_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Détail d\'une table', tags: ['Schema'])]
    public function showTable(string $tenantSlug, int $id): JsonResponse
    {
        [, $schemaDef] = $this->getServices($tenantSlug);

        $table = $schemaDef->getTable($id);

        return $table ? $this->json(['data' => $table]) : $this->json(['error' => 'Table introuvable.'], 404);
    }

    #[Route('/tables/{id}', name: 'schema_tables_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Modifier une table', tags: ['Schema'])]
    public function updateTable(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        [, $schemaDef] = $this->getServices($tenantSlug);

        $data = json_decode($request->getContent(), true) ?? [];
        $table = $schemaDef->updateTable($id, $data);

        return $this->json(['data' => $table]);
    }

    #[Route('/tables/{id}', name: 'schema_tables_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Supprimer une table', tags: ['Schema'])]
    public function deleteTable(string $tenantSlug, int $id): JsonResponse
    {
        [$conn, $schemaDef, $schemaManager] = $this->getServices($tenantSlug);

        $table = $schemaDef->getTable($id);
        if ($table) {
            $schemaManager->dropPhysicalTable($table['name']);
            $schemaDef->deleteTable($id);
        }

        return $this->json(['message' => 'Table supprimée.']);
    }

    // ==================== COLUMNS ====================

    #[Route('/tables/{tableId}/columns', name: 'schema_columns_create', methods: ['POST'], requirements: ['tableId' => '\d+'])]
    #[ApiRoute(summary: 'Ajouter une colonne', tags: ['Schema'])]
    public function createColumn(string $tenantSlug, int $tableId, Request $request): JsonResponse
    {
        [$conn, $schemaDef, $schemaManager] = $this->getServices($tenantSlug);

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $column = $schemaDef->createColumn($tableId, $data);

            // Ajouter physiquement
            $table = $schemaDef->getTable($tableId);
            if ($table && $schemaManager->tableExists($table['name'])) {
                $schemaManager->addPhysicalColumn($table['name'], $column);
            }

            return $this->json(['data' => $column], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/columns/{id}', name: 'schema_columns_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Modifier une colonne', tags: ['Schema'])]
    public function updateColumn(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        [, $schemaDef] = $this->getServices($tenantSlug);

        $data = json_decode($request->getContent(), true) ?? [];
        $column = $schemaDef->updateColumn($id, $data);

        return $column ? $this->json(['data' => $column]) : $this->json(['error' => 'Colonne introuvable.'], 404);
    }

    #[Route('/columns/{id}', name: 'schema_columns_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Supprimer une colonne', tags: ['Schema'])]
    public function deleteColumn(string $tenantSlug, int $id): JsonResponse
    {
        [, $schemaDef] = $this->getServices($tenantSlug);

        try {
            $schemaDef->deleteColumn($id);
            return $this->json(['message' => 'Colonne supprimée.']);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    // ==================== RELATIONS ====================

    #[Route('/relations', name: 'schema_relations_create', methods: ['POST'])]
    #[ApiRoute(summary: 'Créer une relation', tags: ['Schema'])]
    public function createRelation(string $tenantSlug, Request $request): JsonResponse
    {
        [$conn, $schemaDef, $schemaManager] = $this->getServices($tenantSlug);

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $relation = $schemaDef->createRelation($data);

            // Créer la table pivot si many_to_many
            if ($relation['type'] === 'many_to_many' && !empty($relation['pivot_table'])) {
                $source = $schemaDef->getTable((int) $relation['source_table_id']);
                $target = $schemaDef->getTable((int) $relation['target_table_id']);
                $schemaManager->createPivotTable($relation['pivot_table'], $source['name'], $target['name']);
            }

            return $this->json(['data' => $relation], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/relations/{id}', name: 'schema_relations_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Supprimer une relation', tags: ['Schema'])]
    public function deleteRelation(string $tenantSlug, int $id): JsonResponse
    {
        [, $schemaDef] = $this->getServices($tenantSlug);

        $schemaDef->deleteRelation($id);

        return $this->json(['message' => 'Relation supprimée.']);
    }

    // ==================== SYNC ====================

    #[Route('/sync', name: 'schema_sync', methods: ['POST'])]
    #[ApiRoute(summary: 'Synchroniser meta-tables → tables physiques', tags: ['Schema'])]
    public function sync(string $tenantSlug): JsonResponse
    {
        [, , $schemaManager] = $this->getServices($tenantSlug);

        $results = $schemaManager->syncAll();

        return $this->json(['results' => $results]);
    }
}
