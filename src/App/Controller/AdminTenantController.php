<?php

declare(strict_types=1);

namespace App\Controller;

use App\Tenant\TenantService;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\IsGranted;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/admin/tenants')]
#[RequireAuth]
#[IsGranted('ROLE_SUPER_ADMIN')]
class AdminTenantController extends AbstractController
{
    #[Route('', name: 'admin_tenants_list', methods: ['GET'])]
    #[ApiRoute(summary: 'Lister les tenants', tags: ['Admin - Tenants'])]
    public function list(TenantService $tenantService, Request $request): JsonResponse
    {
        $page = (int) ($request->query->get('page') ?? 1);
        $perPage = (int) ($request->query->get('per_page') ?? 20);

        return $this->json([
            'data' => $tenantService->findAll($page, $perPage),
            'total' => $tenantService->count(),
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    #[Route('', name: 'admin_tenants_create', methods: ['POST'])]
    #[ApiRoute(summary: 'Créer un tenant', tags: ['Admin - Tenants'], responses: [201 => 'Créé'])]
    public function create(TenantService $tenantService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom est requis.'], 400);
        }

        // Ajouter l'utilisateur courant comme propriétaire
        $userId = $request->attributes->get('_user_id');
        if ($userId) {
            $data['owner_user_id'] = $userId;
        }

        try {
            $tenant = $tenantService->create($data);

            return $this->json(['data' => $tenant, 'message' => 'Tenant créé.'], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{id}', name: 'admin_tenants_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Détail d\'un tenant', tags: ['Admin - Tenants'])]
    public function show(int $id, TenantService $tenantService): JsonResponse
    {
        $tenant = $tenantService->findById($id);

        if ($tenant === null) {
            return $this->json(['error' => 'Tenant introuvable.'], 404);
        }

        return $this->json([
            'data' => $tenant,
            'users' => $tenantService->getUsers($id),
        ]);
    }

    #[Route('/{id}', name: 'admin_tenants_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Modifier un tenant', tags: ['Admin - Tenants'])]
    public function update(int $id, TenantService $tenantService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $tenant = $tenantService->update($id, $data);

        if ($tenant === null) {
            return $this->json(['error' => 'Tenant introuvable.'], 404);
        }

        return $this->json(['data' => $tenant]);
    }

    #[Route('/{id}', name: 'admin_tenants_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Désactiver un tenant', tags: ['Admin - Tenants'])]
    public function delete(int $id, TenantService $tenantService): JsonResponse
    {
        $tenantService->delete($id);

        return $this->json(['message' => 'Tenant désactivé.']);
    }

    #[Route('/{id}/provision', name: 'admin_tenants_provision', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Provisionner la DB d\'un tenant', tags: ['Admin - Tenants'])]
    public function provision(int $id, TenantService $tenantService): JsonResponse
    {
        try {
            $result = $tenantService->provision($id);

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{id}/users', name: 'admin_tenants_users', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Lister les utilisateurs d\'un tenant', tags: ['Admin - Tenants'])]
    public function users(int $id, TenantService $tenantService): JsonResponse
    {
        return $this->json(['data' => $tenantService->getUsers($id)]);
    }

    #[Route('/{id}/users', name: 'admin_tenants_add_user', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Ajouter un utilisateur à un tenant', tags: ['Admin - Tenants'])]
    public function addUser(int $id, TenantService $tenantService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $userId = (int) ($data['user_id'] ?? 0);
        $roles = $data['roles'] ?? ['ROLE_USER'];

        if ($userId === 0) {
            return $this->json(['error' => 'user_id requis.'], 400);
        }

        $tenantService->addUser($id, $userId, $roles);

        return $this->json(['message' => 'Utilisateur ajouté.'], 201);
    }
}
