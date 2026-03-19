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
use RLSQ\Security\Attribute\RequireAuth;
use RLSQ\Security\Jwt\JwtManager;

/**
 * Authentification des membres dans le contexte d'un tenant.
 */
#[Route('/api/t/{tenantSlug}/auth')]
class TenantAuthController extends AbstractController
{
    private function getTenantConnection(string $tenantSlug): \RLSQ\Database\Connection
    {
        $resolver = $this->get(\App\Tenant\TenantResolver::class);
        $tenant = $resolver->findBySlug($tenantSlug);
        if (!$tenant) {
            throw new \RuntimeException('Tenant introuvable.');
        }

        $factory = $this->get(TenantConnectionFactory::class);
        $ctx = new TenantContext();
        $ctx->setTenant($tenant);

        return $factory->getConnection($ctx);
    }

    #[Route('/me', name: 'tenant_auth_me', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Profil de l\'utilisateur dans le tenant', tags: ['Tenant Auth'])]
    public function me(string $tenantSlug, Request $request): JsonResponse
    {
        $payload = $request->attributes->get('_jwt_payload', []);
        $userId = $payload['user_id'] ?? null;

        if (!$userId) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }

        $conn = $this->getTenantConnection($tenantSlug);

        // Chercher le membre lié
        $member = $conn->fetchOne(
            'SELECT * FROM members WHERE external_user_id = :uid OR email = :email',
            ['uid' => $userId, 'email' => $payload['email'] ?? ''],
        );

        // Déterminer le rôle effectif
        $platformRoles = $payload['roles'] ?? [];
        $effectiveRole = $this->resolveEffectiveRole($platformRoles, $member);

        return $this->json([
            'user' => [
                'id' => $userId,
                'email' => $payload['email'] ?? null,
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
            ],
            'member' => $member ?: null,
            'effective_role' => $effectiveRole,
            'dashboard_type' => $this->dashboardTypeForRole($effectiveRole),
            'platform_roles' => $platformRoles,
        ]);
    }

    #[Route('/role', name: 'tenant_auth_role', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Rôle effectif dans le tenant', tags: ['Tenant Auth'])]
    public function role(string $tenantSlug, Request $request): JsonResponse
    {
        $payload = $request->attributes->get('_jwt_payload', []);
        $platformRoles = $payload['roles'] ?? [];

        $conn = $this->getTenantConnection($tenantSlug);
        $member = $conn->fetchOne(
            'SELECT * FROM members WHERE external_user_id = :uid OR email = :email',
            ['uid' => $payload['user_id'] ?? 0, 'email' => $payload['email'] ?? ''],
        );

        $role = $this->resolveEffectiveRole($platformRoles, $member);

        return $this->json([
            'role' => $role,
            'dashboard_type' => $this->dashboardTypeForRole($role),
            'permissions' => $this->permissionsForRole($role),
        ]);
    }

    /**
     * Hiérarchie :
     *   ROLE_SUPER_ADMIN       → federation (tout accès)
     *   ROLE_TENANT_ADMIN      → federation
     *   ROLE_FEDERATION_ADMIN  → federation
     *   ROLE_CLUB_ADMIN        → club (son club + membres)
     *   ROLE_MEMBER            → member (son espace)
     */
    private function resolveEffectiveRole(array $platformRoles, ?array $member): string
    {
        if (in_array('ROLE_SUPER_ADMIN', $platformRoles, true) || in_array('ROLE_TENANT_ADMIN', $platformRoles, true)) {
            return 'ROLE_FEDERATION_ADMIN';
        }

        if ($member) {
            $memberRoles = json_decode($member['roles'] ?? '[]', true) ?: [];

            if (in_array('ROLE_FEDERATION_ADMIN', $memberRoles, true)) {
                return 'ROLE_FEDERATION_ADMIN';
            }
            if (in_array('ROLE_CLUB_ADMIN', $memberRoles, true)) {
                return 'ROLE_CLUB_ADMIN';
            }
        }

        return 'ROLE_MEMBER';
    }

    private function dashboardTypeForRole(string $role): string
    {
        return match ($role) {
            'ROLE_FEDERATION_ADMIN' => 'federation',
            'ROLE_CLUB_ADMIN' => 'club',
            default => 'member',
        };
    }

    private function permissionsForRole(string $role): array
    {
        $base = ['view_own_profile', 'edit_own_profile'];

        return match ($role) {
            'ROLE_FEDERATION_ADMIN' => array_merge($base, [
                'manage_members', 'manage_clubs', 'manage_plugins', 'manage_settings',
                'manage_dashboards', 'manage_formations', 'manage_activities',
                'manage_rooms', 'view_audit_logs', 'export_data',
            ]),
            'ROLE_CLUB_ADMIN' => array_merge($base, [
                'manage_club_members', 'view_club_stats', 'manage_club_formations',
                'manage_club_activities',
            ]),
            default => $base,
        };
    }
}
