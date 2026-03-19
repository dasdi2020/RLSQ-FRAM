<?php

declare(strict_types=1);

namespace App\Controller;

use App\DatabaseBuilder\DynamicQueryService;
use App\DatabaseBuilder\SchemaDefinitionService;
use App\DatabaseBuilder\ValidationException;
use App\Tenant\TenantContext;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;

/**
 * CRUD dynamique auto-généré pour chaque table du schema builder.
 */
#[Route('/api/t/{tenantSlug}/data')]
#[RequireAuth]
class DynamicDataController extends AbstractController
{
    private function getQueryService(string $tenantSlug): DynamicQueryService
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

        return new DynamicQueryService($conn, $schemaDef);
    }

    #[Route('/{tableName}', name: 'data_list', methods: ['GET'])]
    #[ApiRoute(summary: 'Lister les enregistrements', tags: ['Data'])]
    public function list(string $tenantSlug, string $tableName, Request $request): JsonResponse
    {
        $qs = $this->getQueryService($tenantSlug);

        try {
            $result = $qs->findAll($tableName, [
                'filter' => $request->query->get('filter') ?? [],
                'sort' => $request->query->get('sort') ?? '-created_at',
                'page' => (int) ($request->query->get('page') ?? 1),
                'per_page' => (int) ($request->query->get('per_page') ?? 20),
                'search' => $request->query->get('search') ?? '',
            ]);

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{tableName}/{id}', name: 'data_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Détail d\'un enregistrement', tags: ['Data'])]
    public function show(string $tenantSlug, string $tableName, int $id): JsonResponse
    {
        $qs = $this->getQueryService($tenantSlug);

        $row = $qs->find($tableName, $id);

        return $row ? $this->json(['data' => $row]) : $this->json(['error' => 'Introuvable.'], 404);
    }

    #[Route('/{tableName}', name: 'data_create', methods: ['POST'])]
    #[ApiRoute(summary: 'Créer un enregistrement', tags: ['Data'], responses: [201 => 'Créé'])]
    public function create(string $tenantSlug, string $tableName, Request $request): JsonResponse
    {
        $qs = $this->getQueryService($tenantSlug);
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $row = $qs->create($tableName, $data);

            return $this->json(['data' => $row], 201);
        } catch (ValidationException $e) {
            return $this->json(['error' => 'Validation échouée.', 'errors' => $e->errors], 422);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{tableName}/{id}', name: 'data_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Modifier un enregistrement', tags: ['Data'])]
    public function update(string $tenantSlug, string $tableName, int $id, Request $request): JsonResponse
    {
        $qs = $this->getQueryService($tenantSlug);
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $row = $qs->update($tableName, $id, $data);

            return $row ? $this->json(['data' => $row]) : $this->json(['error' => 'Introuvable.'], 404);
        } catch (ValidationException $e) {
            return $this->json(['error' => 'Validation échouée.', 'errors' => $e->errors], 422);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{tableName}/{id}', name: 'data_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Supprimer un enregistrement', tags: ['Data'])]
    public function delete(string $tenantSlug, string $tableName, int $id): JsonResponse
    {
        $qs = $this->getQueryService($tenantSlug);

        $deleted = $qs->delete($tableName, $id);

        return $deleted ? $this->json(['message' => 'Supprimé.']) : $this->json(['error' => 'Introuvable.'], 404);
    }

    #[Route('/{tableName}/export', name: 'data_export', methods: ['GET'])]
    #[ApiRoute(summary: 'Exporter tous les enregistrements', tags: ['Data'])]
    public function export(string $tenantSlug, string $tableName, Request $request): Response
    {
        $qs = $this->getQueryService($tenantSlug);
        $rows = $qs->export($tableName);
        $format = $request->query->get('format') ?? 'json';

        if ($format === 'csv' && !empty($rows)) {
            $output = implode(',', array_keys($rows[0])) . "\n";
            foreach ($rows as $row) {
                $output .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"', $row)) . "\n";
            }

            return new Response($output, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$tableName}.csv\"",
            ]);
        }

        return $this->json(['data' => $rows]);
    }
}
