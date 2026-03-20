<?php

declare(strict_types=1);

namespace App\Controller;

use App\PageBuilder\PageService;
use App\Tenant\TenantContext;
use App\Tenant\Database\TenantConnectionFactory;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/t/{tenantSlug}/pages')]
class PageBuilderController extends AbstractController
{
    private function getService(string $tenantSlug): PageService
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        if (!$tenant) { throw new \RuntimeException('Tenant introuvable.'); }
        $factory = $this->get(TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);
        return new PageService($factory->getConnection($ctx));
    }

    // ==================== PAGES ====================

    #[Route('', name: 'pages_list', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Lister les pages', tags: ['Pages'])]
    public function list(string $tenantSlug): JsonResponse
    {
        return $this->json(['data' => $this->getService($tenantSlug)->getAllPages()]);
    }

    #[Route('', name: 'pages_create', methods: ['POST'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Créer une page', tags: ['Pages'])]
    public function create(string $tenantSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            return $this->json(['data' => $this->getService($tenantSlug)->createPage($data)], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{id}', name: 'pages_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Détail d\'une page', tags: ['Pages'])]
    public function show(string $tenantSlug, int $id): JsonResponse
    {
        $page = $this->getService($tenantSlug)->getPage($id);
        return $page ? $this->json(['data' => $page]) : $this->json(['error' => 'Page introuvable.'], 404);
    }

    #[Route('/{id}', name: 'pages_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Modifier une page', tags: ['Pages'])]
    public function update(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        return $this->json(['data' => $this->getService($tenantSlug)->updatePage($id, $data)]);
    }

    #[Route('/{id}', name: 'pages_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Supprimer une page', tags: ['Pages'])]
    public function delete(string $tenantSlug, int $id): JsonResponse
    {
        $this->getService($tenantSlug)->deletePage($id);
        return $this->json(['message' => 'Page supprimée.']);
    }

    #[Route('/{id}/duplicate', name: 'pages_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Dupliquer une page', tags: ['Pages'])]
    public function duplicate(string $tenantSlug, int $id): JsonResponse
    {
        $copy = $this->getService($tenantSlug)->duplicatePage($id);
        return $copy ? $this->json(['data' => $copy], 201) : $this->json(['error' => 'Page introuvable.'], 404);
    }

    // ==================== COMPONENTS ====================

    #[Route('/{pageId}/components', name: 'components_add', methods: ['POST'], requirements: ['pageId' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Ajouter un composant', tags: ['Pages'])]
    public function addComponent(string $tenantSlug, int $pageId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        return $this->json(['data' => $this->getService($tenantSlug)->addComponent($pageId, $data)], 201);
    }

    #[Route('/components/{componentId}', name: 'components_update', methods: ['PUT'], requirements: ['componentId' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Modifier un composant', tags: ['Pages'])]
    public function updateComponent(string $tenantSlug, int $componentId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        return $this->json(['data' => $this->getService($tenantSlug)->updateComponent($componentId, $data)]);
    }

    #[Route('/components/{componentId}', name: 'components_delete', methods: ['DELETE'], requirements: ['componentId' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Supprimer un composant', tags: ['Pages'])]
    public function deleteComponent(string $tenantSlug, int $componentId): JsonResponse
    {
        $this->getService($tenantSlug)->deleteComponent($componentId);
        return $this->json(['message' => 'Composant supprimé.']);
    }

    #[Route('/{pageId}/positions', name: 'components_positions', methods: ['PUT'], requirements: ['pageId' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Mettre à jour les positions', tags: ['Pages'])]
    public function updatePositions(string $tenantSlug, int $pageId, Request $request): JsonResponse
    {
        $positions = json_decode($request->getContent(), true) ?? [];
        $this->getService($tenantSlug)->updateComponentPositions($pageId, $positions);
        return $this->json(['message' => 'Positions mises à jour.']);
    }

    // ==================== PREVIEW + EXPORT ====================

    #[Route('/{id}/preview', name: 'pages_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Preview HTML de la page', tags: ['Pages'])]
    public function preview(string $tenantSlug, int $id): Response
    {
        $html = $this->getService($tenantSlug)->renderPage($id);
        if (!$html) {
            return new Response('Page introuvable.', 404);
        }

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    #[Route('/{id}/export/svelte', name: 'pages_export_svelte', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Exporter en code Svelte', tags: ['Pages'])]
    public function exportSvelte(string $tenantSlug, int $id): Response
    {
        $code = $this->getService($tenantSlug)->generateSvelteCode($id);
        if (!$code) {
            return new Response('Page introuvable.', 404);
        }

        return new Response($code, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"page-{$id}.svelte\"",
        ]);
    }
}
