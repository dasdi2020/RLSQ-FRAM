<?php

declare(strict_types=1);

namespace App\Controller;

use App\Deployment\PleskDeployer;
use App\Deployment\StandaloneGenerator;
use App\Tenant\TenantContext;
use App\Tenant\Database\TenantConnectionFactory;
use App\Versioning\SnapshotService;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\IsGranted;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/admin/tenants/{tenantId}')]
#[RequireAuth]
#[IsGranted('ROLE_SUPER_ADMIN')]
class VersioningController extends AbstractController
{
    private function getTenantConnection(int $tenantId): array
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findById($tenantId);
        if (!$tenant) { throw new \RuntimeException('Tenant introuvable.'); }
        $factory = $this->get(TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);
        return [$factory->getConnection($ctx), $tenant];
    }

    // ==================== VERSIONS ====================

    #[Route('/versions', name: 'versions_list', methods: ['GET'], requirements: ['tenantId' => '\d+'])]
    #[ApiRoute(summary: 'Lister les versions', tags: ['Versioning'])]
    public function listVersions(int $tenantId): JsonResponse
    {
        $snapshotService = new SnapshotService($this->get('database.connection'));
        return $this->json(['data' => $snapshotService->getVersionsForTenant($tenantId)]);
    }

    #[Route('/versions', name: 'versions_create', methods: ['POST'], requirements: ['tenantId' => '\d+'])]
    #[ApiRoute(summary: 'Créer un snapshot', tags: ['Versioning'])]
    public function createVersion(int $tenantId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        [$tenantConn] = $this->getTenantConnection($tenantId);
        $snapshotService = new SnapshotService($this->get('database.connection'));

        try {
            $version = $snapshotService->capture(
                $tenantConn, $tenantId,
                $data['version_tag'] ?? 'v' . date('Ymd-His'),
                $data['description'] ?? null,
                $request->attributes->get('_user_id'),
            );
            return $this->json(['data' => [
                'id' => $version['id'], 'version_tag' => $version['version_tag'],
                'status' => $version['status'], 'created_at' => $version['created_at'],
            ]], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/versions/{versionId}', name: 'versions_show', methods: ['GET'], requirements: ['tenantId' => '\d+', 'versionId' => '\d+'])]
    #[ApiRoute(summary: 'Détail d\'une version', tags: ['Versioning'])]
    public function showVersion(int $tenantId, int $versionId): JsonResponse
    {
        $snapshotService = new SnapshotService($this->get('database.connection'));
        $v = $snapshotService->getVersion($versionId);
        if (!$v || (int) $v['tenant_id'] !== $tenantId) {
            return $this->json(['error' => 'Version introuvable.'], 404);
        }

        // Retourner sans le snapshot complet (trop gros), juste le résumé
        $snapshot = json_decode($v['snapshot_data'], true);
        unset($v['snapshot_data']);
        $v['summary'] = [
            'tables' => count($snapshot['meta_tables'] ?? []),
            'forms' => count($snapshot['forms'] ?? []),
            'pages' => count($snapshot['pages'] ?? []),
            'plugins' => count($snapshot['plugins'] ?? []),
            'dashboards' => count($snapshot['dashboards'] ?? []),
        ];

        return $this->json(['data' => $v]);
    }

    #[Route('/versions/{versionId}/restore', name: 'versions_restore', methods: ['POST'], requirements: ['tenantId' => '\d+', 'versionId' => '\d+'])]
    #[ApiRoute(summary: 'Restaurer une version', tags: ['Versioning'])]
    public function restoreVersion(int $tenantId, int $versionId): JsonResponse
    {
        [$tenantConn] = $this->getTenantConnection($tenantId);
        $snapshotService = new SnapshotService($this->get('database.connection'));

        try {
            $result = $snapshotService->restore($tenantConn, $versionId);
            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/versions/{versionId}', name: 'versions_delete', methods: ['DELETE'], requirements: ['tenantId' => '\d+', 'versionId' => '\d+'])]
    #[ApiRoute(summary: 'Supprimer une version', tags: ['Versioning'])]
    public function deleteVersion(int $tenantId, int $versionId): JsonResponse
    {
        $snapshotService = new SnapshotService($this->get('database.connection'));
        $snapshotService->deleteVersion($versionId);
        return $this->json(['message' => 'Version supprimée.']);
    }

    #[Route('/versions/diff', name: 'versions_diff', methods: ['GET'], requirements: ['tenantId' => '\d+'])]
    #[ApiRoute(summary: 'Comparer deux versions', tags: ['Versioning'])]
    public function diff(int $tenantId, Request $request): JsonResponse
    {
        $v1 = (int) $request->query->get('from');
        $v2 = (int) $request->query->get('to');

        $snapshotService = new SnapshotService($this->get('database.connection'));
        try {
            return $this->json(['data' => $snapshotService->diff($v1, $v2)]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    // ==================== DEPLOYMENT ====================

    #[Route('/generate', name: 'deploy_generate', methods: ['POST'], requirements: ['tenantId' => '\d+'])]
    #[ApiRoute(summary: 'Générer un projet standalone', tags: ['Deployment'])]
    public function generate(int $tenantId): JsonResponse
    {
        [$tenantConn, $tenant] = $this->getTenantConnection($tenantId);
        $projectDir = $this->get('service_container')->getParameter('kernel.project_dir');

        $outputDir = $projectDir . '/var/standalone/' . ($tenant['slug'] ?? 'tenant-' . $tenantId);
        $generator = new StandaloneGenerator($projectDir);

        try {
            $result = $generator->generate($tenantConn, $tenant, $outputDir);
            return $this->json($result, 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/deploy', name: 'deploy_plesk', methods: ['POST'], requirements: ['tenantId' => '\d+'])]
    #[ApiRoute(summary: 'Déployer sur Plesk', tags: ['Deployment'])]
    public function deploy(int $tenantId, Request $request): JsonResponse
    {
        $config = json_decode($request->getContent(), true) ?? [];
        $projectDir = $this->get('service_container')->getParameter('kernel.project_dir');

        $pleskHost = $config['plesk_host'] ?? $_ENV['PLESK_HOST'] ?? '';
        $pleskLogin = $config['plesk_login'] ?? $_ENV['PLESK_LOGIN'] ?? '';
        $pleskPassword = $config['plesk_password'] ?? $_ENV['PLESK_PASSWORD'] ?? '';

        if (!$pleskHost) {
            return $this->json(['error' => 'Plesk host requis (config ou .env).'], 422);
        }

        [$tenantConn, $tenant] = $this->getTenantConnection($tenantId);
        $sourceDir = $projectDir . '/var/standalone/' . ($tenant['slug'] ?? 'tenant-' . $tenantId);

        if (!is_dir($sourceDir)) {
            return $this->json(['error' => 'Projet standalone non généré. Appelez /generate d\'abord.'], 422);
        }

        $versionId = (int) ($config['version_id'] ?? 0);
        if ($versionId === 0) {
            return $this->json(['error' => 'version_id requis.'], 422);
        }

        $deployer = new PleskDeployer($pleskHost, $pleskLogin, $pleskPassword);
        $result = $deployer->deploy($sourceDir, $config, $this->get('database.connection'), $tenantId, $versionId);

        $code = $result['status'] === 'live' ? 200 : 422;
        return $this->json($result, $code);
    }

    #[Route('/deployments', name: 'deploy_list', methods: ['GET'], requirements: ['tenantId' => '\d+'])]
    #[ApiRoute(summary: 'Historique des déploiements', tags: ['Deployment'])]
    public function deployments(int $tenantId): JsonResponse
    {
        $deployer = new PleskDeployer('', '', '');
        return $this->json(['data' => $deployer->getDeployments($this->get('database.connection'), $tenantId)]);
    }
}
