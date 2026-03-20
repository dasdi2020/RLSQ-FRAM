<?php

declare(strict_types=1);

namespace App\Controller;

use App\FormBuilder\FormDefinitionService;
use App\Tenant\TenantContext;
use App\Tenant\Database\TenantConnectionFactory;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/t/{tenantSlug}/forms')]
class FormBuilderController extends AbstractController
{
    private function getService(string $tenantSlug): FormDefinitionService
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        if (!$tenant) {
            throw new \RuntimeException('Tenant introuvable.');
        }

        $factory = $this->get(TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);

        return new FormDefinitionService($factory->getConnection($ctx));
    }

    // ==================== FORMS CRUD ====================

    #[Route('', name: 'forms_list', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Lister les formulaires', tags: ['Forms'])]
    public function list(string $tenantSlug): JsonResponse
    {
        return $this->json(['data' => $this->getService($tenantSlug)->getAllForms()]);
    }

    #[Route('', name: 'forms_create', methods: ['POST'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Créer un formulaire', tags: ['Forms'])]
    public function create(string $tenantSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            return $this->json(['data' => $this->getService($tenantSlug)->createForm($data)], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{id}', name: 'forms_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Détail d\'un formulaire', tags: ['Forms'])]
    public function show(string $tenantSlug, int $id): JsonResponse
    {
        $form = $this->getService($tenantSlug)->getForm($id);

        return $form ? $this->json(['data' => $form]) : $this->json(['error' => 'Formulaire introuvable.'], 404);
    }

    #[Route('/{id}', name: 'forms_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Modifier un formulaire', tags: ['Forms'])]
    public function update(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->json(['data' => $this->getService($tenantSlug)->updateForm($id, $data)]);
    }

    #[Route('/{id}', name: 'forms_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Supprimer un formulaire', tags: ['Forms'])]
    public function delete(string $tenantSlug, int $id): JsonResponse
    {
        $this->getService($tenantSlug)->deleteForm($id);

        return $this->json(['message' => 'Formulaire supprimé.']);
    }

    // ==================== FIELDS ====================

    #[Route('/{formId}/fields', name: 'fields_add', methods: ['POST'], requirements: ['formId' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Ajouter un champ', tags: ['Forms'])]
    public function addField(string $tenantSlug, int $formId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            return $this->json(['data' => $this->getService($tenantSlug)->addField($formId, $data)], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/fields/{fieldId}', name: 'fields_update', methods: ['PUT'], requirements: ['fieldId' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Modifier un champ', tags: ['Forms'])]
    public function updateField(string $tenantSlug, int $fieldId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->json(['data' => $this->getService($tenantSlug)->updateField($fieldId, $data)]);
    }

    #[Route('/fields/{fieldId}', name: 'fields_delete', methods: ['DELETE'], requirements: ['fieldId' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Supprimer un champ', tags: ['Forms'])]
    public function deleteField(string $tenantSlug, int $fieldId): JsonResponse
    {
        $this->getService($tenantSlug)->deleteField($fieldId);

        return $this->json(['message' => 'Champ supprimé.']);
    }

    #[Route('/{formId}/fields/reorder', name: 'fields_reorder', methods: ['PUT'], requirements: ['formId' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Réordonner les champs', tags: ['Forms'])]
    public function reorderFields(string $tenantSlug, int $formId, Request $request): JsonResponse
    {
        $order = json_decode($request->getContent(), true) ?? [];
        $this->getService($tenantSlug)->reorderFields($formId, $order);

        return $this->json(['message' => 'Ordre mis à jour.']);
    }

    // ==================== RENDER + SUBMIT ====================

    #[Route('/{slug}/render', name: 'forms_render', methods: ['GET'])]
    #[ApiRoute(summary: 'Structure JSON pour afficher le formulaire', tags: ['Forms'])]
    public function render(string $tenantSlug, string $slug): JsonResponse
    {
        $service = $this->getService($tenantSlug);
        $form = $service->getFormBySlug($slug);

        if (!$form) {
            return $this->json(['error' => 'Formulaire introuvable.'], 404);
        }

        return $this->json(['data' => $service->renderForm((int) $form['id'])]);
    }

    #[Route('/{slug}/submit', name: 'forms_submit', methods: ['POST'])]
    #[ApiRoute(summary: 'Soumettre un formulaire', tags: ['Forms'])]
    public function submit(string $tenantSlug, string $slug, Request $request): JsonResponse
    {
        $service = $this->getService($tenantSlug);
        $form = $service->getFormBySlug($slug);

        if (!$form) {
            return $this->json(['error' => 'Formulaire introuvable.'], 404);
        }

        if (!$form['is_published']) {
            return $this->json(['error' => 'Formulaire non publié.'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $userId = $request->attributes->get('_user_id');
        $ip = $request->getClientIp();

        $result = $service->submit((int) $form['id'], $data, $userId, $ip);

        if (!$result['success']) {
            return $this->json(['error' => 'Validation échouée.', 'errors' => $result['errors']], 422);
        }

        return $this->json($result, 201);
    }

    // ==================== SUBMISSIONS ====================

    #[Route('/{id}/submissions', name: 'forms_submissions', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Lister les soumissions', tags: ['Forms'])]
    public function submissions(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        $page = (int) ($request->query->get('page') ?? 1);
        $perPage = (int) ($request->query->get('per_page') ?? 20);

        return $this->json($this->getService($tenantSlug)->getSubmissions($id, $page, $perPage));
    }
}
