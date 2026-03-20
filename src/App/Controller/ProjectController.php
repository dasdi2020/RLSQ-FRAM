<?php

declare(strict_types=1);

namespace App\Controller;

use App\Project\ProjectService;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\IsGranted;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/projects')]
#[RequireAuth]
class ProjectController extends AbstractController
{
    #[Route('', name: 'projects_list', methods: ['GET'])]
    #[ApiRoute(summary: 'Lister les projets', tags: ['Projects'])]
    public function list(ProjectService $projectService, Request $request): JsonResponse
    {
        $tenantId = $request->query->get('tenant_id') ? (int) $request->query->get('tenant_id') : null;
        $page = (int) ($request->query->get('page') ?? 1);

        return $this->json([
            'data' => $projectService->findAll($tenantId, $page),
            'total' => $projectService->count($tenantId),
            'page' => $page,
        ]);
    }

    #[Route('', name: 'projects_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[ApiRoute(summary: 'Créer un projet', tags: ['Projects'])]
    public function create(ProjectService $projectService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $data['created_by'] = $request->attributes->get('_user_id');

        try {
            $project = $projectService->create($data);

            return $this->json(['data' => $project, 'message' => 'Projet créé.'], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{id}', name: 'projects_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Détail d\'un projet', tags: ['Projects'])]
    public function show(int $id, ProjectService $projectService): JsonResponse
    {
        $project = $projectService->findById($id);

        return $project ? $this->json(['data' => $project]) : $this->json(['error' => 'Projet introuvable.'], 404);
    }

    #[Route('/{id}', name: 'projects_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    #[ApiRoute(summary: 'Modifier un projet', tags: ['Projects'])]
    public function update(int $id, ProjectService $projectService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->json(['data' => $projectService->update($id, $data)]);
    }

    #[Route('/{id}', name: 'projects_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[ApiRoute(summary: 'Supprimer un projet', tags: ['Projects'])]
    public function delete(int $id, ProjectService $projectService): JsonResponse
    {
        $projectService->delete($id);

        return $this->json(['message' => 'Projet supprimé.']);
    }

    #[Route('/{id}/provision', name: 'projects_provision', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    #[ApiRoute(summary: 'Provisionner la DB du projet', tags: ['Projects'])]
    public function provision(int $id, ProjectService $projectService): JsonResponse
    {
        try {
            return $this->json($projectService->provision($id));
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }
}
