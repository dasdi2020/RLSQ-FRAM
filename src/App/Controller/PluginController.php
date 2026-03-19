<?php

declare(strict_types=1);

namespace App\Controller;

use App\Tenant\TenantContext;
use App\Tenant\Database\TenantConnectionFactory;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Plugin\PluginManager;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/t/{tenantSlug}/plugins')]
#[RequireAuth]
class PluginController extends AbstractController
{
    private function getTenantConnection(string $tenantSlug): \RLSQ\Database\Connection
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        if ($tenant === null) {
            throw new \RuntimeException('Tenant introuvable.');
        }

        $factory = $this->get(TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);

        return $factory->getConnection($ctx);
    }

    #[Route('', name: 'plugins_list', methods: ['GET'])]
    #[ApiRoute(summary: 'Lister les plugins avec statut', tags: ['Plugins'])]
    public function list(string $tenantSlug, PluginManager $pluginManager): JsonResponse
    {
        $conn = $this->getTenantConnection($tenantSlug);

        return $this->json(['data' => $pluginManager->getPluginsWithStatus($conn)]);
    }

    #[Route('/{slug}/install', name: 'plugins_install', methods: ['POST'])]
    #[ApiRoute(summary: 'Installer un plugin', tags: ['Plugins'])]
    public function install(string $tenantSlug, string $slug, PluginManager $pluginManager, Request $request): JsonResponse
    {
        $conn = $this->getTenantConnection($tenantSlug);
        $settings = json_decode($request->getContent(), true) ?? [];

        try {
            $state = $pluginManager->install($slug, $conn, $settings);

            return $this->json(['data' => $state, 'message' => 'Plugin installé.'], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{slug}/uninstall', name: 'plugins_uninstall', methods: ['POST'])]
    #[ApiRoute(summary: 'Désinstaller un plugin', tags: ['Plugins'])]
    public function uninstall(string $tenantSlug, string $slug, PluginManager $pluginManager): JsonResponse
    {
        $conn = $this->getTenantConnection($tenantSlug);

        try {
            $pluginManager->uninstall($slug, $conn);

            return $this->json(['message' => 'Plugin désinstallé.']);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/{slug}/activate', name: 'plugins_activate', methods: ['POST'])]
    #[ApiRoute(summary: 'Activer un plugin', tags: ['Plugins'])]
    public function activate(string $tenantSlug, string $slug, PluginManager $pluginManager): JsonResponse
    {
        $conn = $this->getTenantConnection($tenantSlug);
        $pluginManager->activate($slug, $conn);

        return $this->json(['message' => 'Plugin activé.']);
    }

    #[Route('/{slug}/deactivate', name: 'plugins_deactivate', methods: ['POST'])]
    #[ApiRoute(summary: 'Désactiver un plugin', tags: ['Plugins'])]
    public function deactivate(string $tenantSlug, string $slug, PluginManager $pluginManager): JsonResponse
    {
        $conn = $this->getTenantConnection($tenantSlug);
        $pluginManager->deactivate($slug, $conn);

        return $this->json(['message' => 'Plugin désactivé.']);
    }

    #[Route('/{slug}/settings', name: 'plugins_settings', methods: ['PUT'])]
    #[ApiRoute(summary: 'Modifier les settings d\'un plugin', tags: ['Plugins'])]
    public function updateSettings(string $tenantSlug, string $slug, PluginManager $pluginManager, Request $request): JsonResponse
    {
        $conn = $this->getTenantConnection($tenantSlug);
        $settings = json_decode($request->getContent(), true) ?? [];

        $pluginManager->updateSettings($slug, $conn, $settings);

        return $this->json(['message' => 'Settings mis à jour.']);
    }
}
