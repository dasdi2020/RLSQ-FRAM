<?php

declare(strict_types=1);

namespace App\Tenant;

use RLSQ\Database\Connection;
use RLSQ\HttpFoundation\Request;

/**
 * Détermine le tenant depuis la requête HTTP.
 *
 * Stratégies (dans l'ordre) :
 *   1. Header X-Tenant-ID ou X-Tenant-Slug
 *   2. Segment d'URL /t/{tenantSlug}/...
 *   3. Sous-domaine : {slug}.plateforme.com
 */
class TenantResolver
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function resolve(Request $request): ?array
    {
        // 1. Header
        $slug = $request->headers->get('x-tenant-slug');
        if ($slug !== null) {
            return $this->findBySlug($slug);
        }

        $id = $request->headers->get('x-tenant-id');
        if ($id !== null) {
            return $this->findById((int) $id);
        }

        // 2. Segment d'URL /t/{slug}/...
        $path = $request->getPathInfo();
        if (preg_match('#^/t/([a-z0-9\-]+)/#', $path, $m)) {
            return $this->findBySlug($m[1]);
        }

        // 3. Sous-domaine
        $host = $request->getHost();
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            if ($subdomain !== 'www' && $subdomain !== 'api') {
                return $this->findBySlug($subdomain);
            }
        }

        return null;
    }

    public function findBySlug(string $slug): ?array
    {
        $tenant = $this->connection->fetchOne(
            'SELECT * FROM tenants WHERE slug = :slug AND is_active = 1',
            ['slug' => $slug],
        );

        return $tenant ?: null;
    }

    public function findById(int $id): ?array
    {
        $tenant = $this->connection->fetchOne(
            'SELECT * FROM tenants WHERE id = :id AND is_active = 1',
            ['id' => $id],
        );

        return $tenant ?: null;
    }

    /**
     * @return array[]
     */
    public function findAll(): array
    {
        return $this->connection->fetchAll('SELECT * FROM tenants ORDER BY name ASC');
    }
}
