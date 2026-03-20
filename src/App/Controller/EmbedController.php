<?php

declare(strict_types=1);

namespace App\Controller;

use App\Embed\EmbedRenderer;
use App\Embed\EmbedService;
use App\Tenant\TenantContext;
use App\Tenant\Database\TenantConnectionFactory;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;

class EmbedController extends AbstractController
{
    private function getServiceForTenant(string $tenantSlug): array
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        if (!$tenant) { throw new \RuntimeException('Tenant introuvable.'); }
        $factory = $this->get(TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);
        $conn = $factory->getConnection($ctx);
        return [new EmbedService($conn), $conn, $tenant];
    }

    // ==================== Admin API (config) ====================

    #[Route('/api/t/{tenantSlug}/embeds', name: 'embeds_list', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Lister les embeds', tags: ['Embed'])]
    public function list(string $tenantSlug): JsonResponse
    {
        [$service] = $this->getServiceForTenant($tenantSlug);
        return $this->json(['data' => $service->getAllEmbeds()]);
    }

    #[Route('/api/t/{tenantSlug}/embeds', name: 'embeds_create', methods: ['POST'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Créer un embed', tags: ['Embed'])]
    public function create(string $tenantSlug, Request $request): JsonResponse
    {
        [$service] = $this->getServiceForTenant($tenantSlug);
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $embed = $service->createEmbed($data);
            $baseUrl = $this->getBaseUrl($request);
            $embed['snippet'] = $service->generateSnippet($embed, $baseUrl);
            return $this->json(['data' => $embed], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/api/t/{tenantSlug}/embeds/{id}', name: 'embeds_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Détail d\'un embed', tags: ['Embed'])]
    public function show(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        [$service] = $this->getServiceForTenant($tenantSlug);
        $embed = $service->getEmbed($id);
        if (!$embed) { return $this->json(['error' => 'Embed introuvable.'], 404); }

        $embed['snippet'] = $service->generateSnippet($embed, $this->getBaseUrl($request));
        return $this->json(['data' => $embed]);
    }

    #[Route('/api/t/{tenantSlug}/embeds/{id}', name: 'embeds_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Modifier un embed', tags: ['Embed'])]
    public function update(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        [$service] = $this->getServiceForTenant($tenantSlug);
        $data = json_decode($request->getContent(), true) ?? [];
        return $this->json(['data' => $service->updateEmbed($id, $data)]);
    }

    #[Route('/api/t/{tenantSlug}/embeds/{id}', name: 'embeds_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Supprimer un embed', tags: ['Embed'])]
    public function delete(string $tenantSlug, int $id): JsonResponse
    {
        [$service] = $this->getServiceForTenant($tenantSlug);
        $service->deleteEmbed($id);
        return $this->json(['message' => 'Embed supprimé.']);
    }

    #[Route('/api/t/{tenantSlug}/embeds/{id}/regenerate-token', name: 'embeds_regen', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Régénérer le token', tags: ['Embed'])]
    public function regenerateToken(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        [$service] = $this->getServiceForTenant($tenantSlug);
        $embed = $service->regenerateToken($id);
        if (!$embed) { return $this->json(['error' => 'Embed introuvable.'], 404); }

        $embed['snippet'] = $service->generateSnippet($embed, $this->getBaseUrl($request));
        return $this->json(['data' => $embed]);
    }

    #[Route('/api/t/{tenantSlug}/embeds/{id}/snippet', name: 'embeds_snippet', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Obtenir le snippet JS', tags: ['Embed'])]
    public function snippet(string $tenantSlug, int $id, Request $request): JsonResponse
    {
        [$service] = $this->getServiceForTenant($tenantSlug);
        $embed = $service->getEmbed($id);
        if (!$embed) { return $this->json(['error' => 'Embed introuvable.'], 404); }

        return $this->json(['snippet' => $service->generateSnippet($embed, $this->getBaseUrl($request))]);
    }

    // ==================== Public : iframe content ====================

    #[Route('/embed/{token}', name: 'embed_render', methods: ['GET'])]
    #[ApiRoute(summary: 'Contenu de l\'iframe embed (public)', tags: ['Embed'])]
    public function render(string $token, Request $request): Response
    {
        // Trouver à quel tenant appartient ce token
        $embedData = $this->findEmbedByToken($token);
        if (!$embedData) {
            return new Response('<p>Embed introuvable ou désactivé.</p>', 404, ['Content-Type' => 'text/html']);
        }

        [$embed, $tenantConn] = $embedData;

        // Vérifier le domaine d'origine
        $origin = $request->headers->get('origin') ?? $request->headers->get('referer') ?? '';
        $embedService = new EmbedService($tenantConn);
        if ($origin && !$embedService->isDomainAllowed($embed, $origin)) {
            return new Response('<p>Domaine non autorisé.</p>', 403, ['Content-Type' => 'text/html']);
        }

        $renderer = new EmbedRenderer($tenantConn);
        $html = $renderer->render($embed);

        $headers = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'ALLOWALL',
        ];
        if ($origin) {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }

        return new Response($html, 200, $headers);
    }

    // ==================== PRIVATE ====================

    private function findEmbedByToken(string $token): ?array
    {
        // Parcourir les tenants pour trouver l'embed
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $factory = $this->get(TenantConnectionFactory::class);

        $tenants = $resolver->findAll();

        foreach ($tenants as $tenant) {
            if (!(int) $tenant['is_provisioned']) {
                continue;
            }

            try {
                $ctx = new TenantContext();
                $ctx->setTenant($tenant);
                $conn = $factory->getConnection($ctx);

                $service = new EmbedService($conn);
                $embed = $service->getEmbedByToken($token);

                if ($embed) {
                    return [$embed, $conn];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function getBaseUrl(Request $request): string
    {
        return $request->getScheme() . '://' . $request->getHost() . ($request->getPort() !== 80 && $request->getPort() !== 443 ? ':' . $request->getPort() : '');
    }
}
