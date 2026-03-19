<?php

declare(strict_types=1);

namespace App\Tenant;

use App\Tenant\Database\TenantDatabaseProvisioner;
use RLSQ\Database\Connection;

/**
 * Service CRUD et gestion des tenants.
 */
class TenantService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantDatabaseProvisioner $provisioner,
        private readonly string $projectDir,
    ) {}

    /**
     * Crée un nouveau tenant.
     */
    public function create(array $data): array
    {
        $slug = $data['slug'] ?? $this->slugify($data['name'] ?? 'tenant');

        // Vérifier l'unicité du slug
        $existing = $this->connection->fetchOne('SELECT id FROM tenants WHERE slug = :s', ['s' => $slug]);
        if ($existing) {
            throw new \RuntimeException(sprintf('Le slug "%s" est déjà utilisé.', $slug));
        }

        // Par défaut, DB SQLite dans var/tenants/{slug}.sqlite
        $dbPath = $data['db_path'] ?? 'var/tenants/' . $slug . '.sqlite';

        $this->connection->execute(
            'INSERT INTO tenants (slug, name, type, domain, db_driver, db_host, db_port, db_name, db_user, db_password, db_path, settings)
             VALUES (:slug, :name, :type, :domain, :driver, :host, :port, :dbname, :user, :pass, :path, :settings)',
            [
                'slug' => $slug,
                'name' => $data['name'] ?? $slug,
                'type' => $data['type'] ?? 'organization',
                'domain' => $data['domain'] ?? null,
                'driver' => $data['db_driver'] ?? 'sqlite',
                'host' => $data['db_host'] ?? 'localhost',
                'port' => $data['db_port'] ?? '3306',
                'dbname' => $data['db_name'] ?? '',
                'user' => $data['db_user'] ?? '',
                'pass' => $data['db_password'] ?? '',
                'path' => $dbPath,
                'settings' => json_encode($data['settings'] ?? []),
            ],
        );

        $id = (int) $this->connection->lastInsertId();

        // Associer le créateur comme admin principal
        if (isset($data['owner_user_id'])) {
            $this->addUser($id, (int) $data['owner_user_id'], ['ROLE_TENANT_ADMIN'], true);
        }

        return $this->findById($id);
    }

    /**
     * Provisionne la base de données d'un tenant.
     */
    public function provision(int $tenantId): array
    {
        $tenant = $this->findById($tenantId);

        if ($tenant === null) {
            throw new \RuntimeException('Tenant introuvable.');
        }

        if ($tenant['is_provisioned']) {
            throw new \RuntimeException('Tenant déjà provisionné.');
        }

        $dbConfig = [
            'driver' => $tenant['db_driver'],
            'host' => $tenant['db_host'],
            'port' => $tenant['db_port'],
            'dbname' => $tenant['db_name'],
            'user' => $tenant['db_user'],
            'password' => $tenant['db_password'],
            'path' => $tenant['db_path'],
        ];

        $result = $this->provisioner->provision($dbConfig, [
            'tenant_id' => $tenantId,
            'tenant_slug' => $tenant['slug'],
            'tenant_name' => $tenant['name'],
        ]);

        // Marquer comme provisionné
        $this->connection->execute(
            'UPDATE tenants SET is_provisioned = 1, updated_at = :now WHERE id = :id',
            ['id' => $tenantId, 'now' => date('Y-m-d H:i:s')],
        );

        return [
            'tenant_id' => $tenantId,
            'migrations_run' => $result['migrations_run'],
            'status' => 'provisioned',
        ];
    }

    public function findById(int $id): ?array
    {
        $t = $this->connection->fetchOne('SELECT * FROM tenants WHERE id = :id', ['id' => $id]);

        return $t ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $t = $this->connection->fetchOne('SELECT * FROM tenants WHERE slug = :s', ['s' => $slug]);

        return $t ?: null;
    }

    /**
     * @return array[]
     */
    public function findAll(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        return $this->connection->fetchAll(
            'SELECT * FROM tenants ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            ['limit' => $perPage, 'offset' => $offset],
        );
    }

    public function count(): int
    {
        return (int) $this->connection->fetchColumn('SELECT COUNT(*) FROM tenants');
    }

    public function update(int $id, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $id];

        $allowed = ['name', 'type', 'domain', 'is_active', 'settings', 'logo_url', 'primary_color',
            'db_driver', 'db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'db_path'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if ($field === 'settings' && is_array($value)) {
                    $value = json_encode($value);
                }
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($sets)) {
            return $this->findById($id);
        }

        $sets[] = 'updated_at = :now';
        $params['now'] = date('Y-m-d H:i:s');

        $this->connection->execute(
            'UPDATE tenants SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params,
        );

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        // Soft delete — désactiver
        $this->connection->execute(
            'UPDATE tenants SET is_active = 0, updated_at = :now WHERE id = :id',
            ['id' => $id, 'now' => date('Y-m-d H:i:s')],
        );

        return true;
    }

    // --- Gestion des utilisateurs du tenant ---

    public function addUser(int $tenantId, int $userId, array $roles = ['ROLE_USER'], bool $isPrimary = false): void
    {
        $this->connection->execute(
            'INSERT OR IGNORE INTO tenant_users (tenant_id, user_id, roles, is_primary) VALUES (:tid, :uid, :roles, :primary)',
            ['tid' => $tenantId, 'uid' => $userId, 'roles' => json_encode($roles), 'primary' => $isPrimary ? 1 : 0],
        );
    }

    public function removeUser(int $tenantId, int $userId): void
    {
        $this->connection->execute(
            'DELETE FROM tenant_users WHERE tenant_id = :tid AND user_id = :uid',
            ['tid' => $tenantId, 'uid' => $userId],
        );
    }

    /**
     * @return array[]
     */
    public function getUsers(int $tenantId): array
    {
        return $this->connection->fetchAll(
            'SELECT tu.*, u.email, u.first_name, u.last_name
             FROM tenant_users tu JOIN users u ON u.id = tu.user_id
             WHERE tu.tenant_id = :tid ORDER BY tu.is_primary DESC, u.first_name ASC',
            ['tid' => $tenantId],
        );
    }

    /**
     * Retourne les tenants auxquels un utilisateur a accès.
     */
    public function getTenantsForUser(int $userId): array
    {
        return $this->connection->fetchAll(
            'SELECT t.*, tu.roles as user_roles, tu.is_primary
             FROM tenants t JOIN tenant_users tu ON tu.tenant_id = t.id
             WHERE tu.user_id = :uid AND t.is_active = 1 ORDER BY t.name ASC',
            ['uid' => $userId],
        );
    }

    private function slugify(string $text): string
    {
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', trim($text));
        } else {
            $text = strtolower(trim($text));
            $text = strtr($text, 'àâäéèêëïîôùûüÿçñ', 'aaaeeeeiioouuyçn');
        }

        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }
}
