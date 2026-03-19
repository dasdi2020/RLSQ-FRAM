<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dashboard\DashboardService;
use App\Tenant\TenantContext;
use App\Tenant\Database\TenantConnectionFactory;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/t/{tenantSlug}/dashboards')]
#[RequireAuth]
class DashboardController extends AbstractController
{
    private function getService(string $tenantSlug): DashboardService
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        if (!$tenant) {
            throw new \RuntimeException('Tenant introuvable.');
        }

        $factory = $this->get(TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);

        return new DashboardService($factory->getConnection($ctx));
    }

    #[Route('', name: 'dashboards_list', methods: ['GET'])]
    #[ApiRoute(summary: 'Lister les dashboards', tags: ['Dashboards'])]
    public function list(string $tenantSlug): JsonResponse
    {
        return $this->json(['data' => $this->getService($tenantSlug)->getAllDashboards()]);
    }

    #[Route('/my', name: 'dashboards_my', methods: ['GET'])]
    #[ApiRoute(summary: 'Dashboard par défaut pour mon rôle', tags: ['Dashboards'])]
    public function my(string $tenantSlug, Request $request): JsonResponse
    {
        $service = $this->getService($tenantSlug);
        $payload = $request->attributes->get('_jwt_payload', []);
        $roles = $payload['roles'] ?? ['ROLE_MEMBER'];

        // Trouver le dashboard qui correspond au rôle le plus élevé
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_TENANT_ADMIN', 'ROLE_FEDERATION_ADMIN'] as $r) {
            if (in_array($r, $roles, true)) {
                $d = $service->getDefaultForRole('ROLE_FEDERATION_ADMIN') ?? $service->getDefaultForRole('ROLE_TENANT_ADMIN');
                if ($d) {
                    return $this->json(['data' => $d]);
                }
            }
        }

        if (in_array('ROLE_CLUB_ADMIN', $roles, true)) {
            $d = $service->getDefaultForRole('ROLE_CLUB_ADMIN');
            if ($d) {
                return $this->json(['data' => $d]);
            }
        }

        $d = $service->getDefaultForRole('ROLE_MEMBER');

        return $this->json(['data' => $d]);
    }

    #[Route('/{id}', name: 'dashboards_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Détail d\'un dashboard', tags: ['Dashboards'])]
    public function show(string $tenantSlug, int $id): JsonResponse
    {
        $d = $this->getService($tenantSlug)->getDashboard($id);

        return $d ? $this->json(['data' => $d]) : $this->json(['error' => 'Dashboard introuvable.'], 404);
    }

    #[Route('', name: 'dashboards_create', methods: ['POST'])]
    #[ApiRoute(summary: 'Créer un dashboard', tags: ['Dashboards'])]
    public function create(string $tenantSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->json(['data' => $this->getService($tenantSlug)->createDashboard($data)], 201);
    }

    #[Route('/{id}', name: 'dashboards_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Modifier un dashboard', tags: ['Dashboards'])]
    public function update(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->json(['data' => $this->getService($tenantSlug)->updateDashboard($id, $data)]);
    }

    #[Route('/{id}', name: 'dashboards_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Supprimer un dashboard', tags: ['Dashboards'])]
    public function delete(string $tenantSlug, int $id): JsonResponse
    {
        $this->getService($tenantSlug)->deleteDashboard($id);

        return $this->json(['message' => 'Dashboard supprimé.']);
    }

    // ==================== WIDGETS ====================

    #[Route('/{id}/widgets', name: 'widgets_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Ajouter un widget', tags: ['Dashboards'])]
    public function addWidget(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->json(['data' => $this->getService($tenantSlug)->addWidget($id, $data)], 201);
    }

    #[Route('/widgets/{widgetId}', name: 'widgets_update', methods: ['PUT'], requirements: ['widgetId' => '\d+'])]
    #[ApiRoute(summary: 'Modifier un widget', tags: ['Dashboards'])]
    public function updateWidget(string $tenantSlug, int $widgetId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->json(['data' => $this->getService($tenantSlug)->updateWidget($widgetId, $data)]);
    }

    #[Route('/widgets/{widgetId}', name: 'widgets_delete', methods: ['DELETE'], requirements: ['widgetId' => '\d+'])]
    #[ApiRoute(summary: 'Supprimer un widget', tags: ['Dashboards'])]
    public function deleteWidget(string $tenantSlug, int $widgetId): JsonResponse
    {
        $this->getService($tenantSlug)->deleteWidget($widgetId);

        return $this->json(['message' => 'Widget supprimé.']);
    }

    #[Route('/{id}/positions', name: 'widgets_positions', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Mettre à jour les positions des widgets', tags: ['Dashboards'])]
    public function updatePositions(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        $positions = json_decode($request->getContent(), true) ?? [];
        $this->getService($tenantSlug)->updateWidgetPositions($id, $positions);

        return $this->json(['message' => 'Positions mises à jour.']);
    }

    // ==================== WIDGET DATA ====================

    #[Route('/data/{widgetId}', name: 'widget_data', methods: ['GET'], requirements: ['widgetId' => '\d+'])]
    #[ApiRoute(summary: 'Données d\'un widget', tags: ['Dashboards'])]
    public function widgetData(string $tenantSlug, int $widgetId): JsonResponse
    {
        $service = $this->getService($tenantSlug);

        $widget = $this->getService($tenantSlug)->getWidgets(0); // On va chercher directement
        $conn = $this->get(TenantConnectionFactory::class);

        // Récupérer le widget
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);
        $tenantConn = $this->get(TenantConnectionFactory::class)->getConnection($ctx);

        $w = $tenantConn->fetchOne('SELECT * FROM dashboard_widgets WHERE id = :id', ['id' => $widgetId]);

        if (!$w) {
            return $this->json(['error' => 'Widget introuvable.'], 404);
        }

        $w['config'] = json_decode($w['config'] ?? '{}', true);
        $data = $service->resolveWidgetData($w);

        return $this->json(['data' => $data]);
    }
}
