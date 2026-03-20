<?php

declare(strict_types=1);

namespace App\Project;

use App\Tenant\Database\TenantDatabaseProvisioner;
use RLSQ\Database\Connection;

class ProjectService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantDatabaseProvisioner $provisioner,
        private readonly string $projectDir,
    ) {}

    public function create(array $data): array
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        if ($tenantId === 0) {
            throw new \InvalidArgumentException('tenant_id requis.');
        }

        $name = $data['name'] ?? throw new \InvalidArgumentException('name requis.');
        $slug = $data['slug'] ?? $this->slugify($name);

        $existing = $this->connection->fetchOne('SELECT id FROM projects WHERE slug = :s', ['s' => $slug]);
        if ($existing) {
            throw new \RuntimeException(sprintf('Le slug "%s" est déjà utilisé.', $slug));
        }

        $type = $data['type'] ?? 'website';
        if (!in_array($type, ['website', 'webapp'], true)) {
            throw new \InvalidArgumentException('type doit être "website" ou "webapp".');
        }

        // DB par défaut : SQLite dans var/projects/{slug}.sqlite
        $dbPath = $data['db_path'] ?? 'var/projects/' . $slug . '.sqlite';

        $this->connection->execute(
            'INSERT INTO projects (tenant_id, name, slug, type, dns_address, db_driver, db_host, db_port, db_name, db_user, db_password, db_path, settings, template_config, login_config, created_by)
             VALUES (:tid, :n, :s, :t, :dns, :drv, :h, :p, :dbn, :dbu, :dbp, :dbpath, :set, :tc, :lc, :cb)',
            [
                'tid' => $tenantId, 'n' => $name, 's' => $slug, 't' => $type,
                'dns' => $data['dns_address'] ?? null,
                'drv' => $data['db_driver'] ?? 'sqlite',
                'h' => $data['db_host'] ?? 'localhost',
                'p' => $data['db_port'] ?? '3306',
                'dbn' => $data['db_name'] ?? '',
                'dbu' => $data['db_user'] ?? '',
                'dbp' => $data['db_password'] ?? '',
                'dbpath' => $dbPath,
                'set' => json_encode($data['settings'] ?? []),
                'tc' => json_encode($this->getDefaultTemplateConfig($type)),
                'lc' => json_encode($this->getDefaultLoginConfig()),
                'cb' => $data['created_by'] ?? null,
            ],
        );

        return $this->findById((int) $this->connection->lastInsertId());
    }

    public function provision(int $projectId): array
    {
        $project = $this->findById($projectId);
        if (!$project) {
            throw new \RuntimeException('Projet introuvable.');
        }
        if ($project['is_provisioned']) {
            throw new \RuntimeException('Projet déjà provisionné.');
        }

        $dbConfig = [
            'driver' => $project['db_driver'],
            'host' => $project['db_host'],
            'port' => $project['db_port'],
            'dbname' => $project['db_name'],
            'user' => $project['db_user'],
            'password' => $project['db_password'],
            'path' => $project['db_path'],
        ];

        $result = $this->provisioner->provision($dbConfig, [
            'project_id' => $projectId,
            'project_slug' => $project['slug'],
            'project_name' => $project['name'],
            'project_type' => $project['type'],
        ]);

        $this->connection->execute(
            'UPDATE projects SET is_provisioned = 1, status = :s, updated_at = :now WHERE id = :id',
            ['s' => 'active', 'now' => date('Y-m-d H:i:s'), 'id' => $projectId],
        );

        return ['project_id' => $projectId, 'migrations_run' => $result['migrations_run'], 'status' => 'provisioned'];
    }

    public function findById(int $id): ?array
    {
        $p = $this->connection->fetchOne('SELECT * FROM projects WHERE id = :id', ['id' => $id]);
        if ($p) {
            $p['settings'] = json_decode($p['settings'] ?? '{}', true);
            $p['template_config'] = json_decode($p['template_config'] ?? '{}', true);
            $p['login_config'] = json_decode($p['login_config'] ?? '{}', true);
        }

        return $p ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $p = $this->connection->fetchOne('SELECT * FROM projects WHERE slug = :s', ['s' => $slug]);
        if ($p) {
            $p['settings'] = json_decode($p['settings'] ?? '{}', true);
            $p['template_config'] = json_decode($p['template_config'] ?? '{}', true);
            $p['login_config'] = json_decode($p['login_config'] ?? '{}', true);
        }

        return $p ?: null;
    }

    /** @return array[] */
    public function findAll(?int $tenantId = null, int $page = 1, int $perPage = 20): array
    {
        $where = $tenantId !== null ? 'WHERE tenant_id = :tid' : '';
        $params = $tenantId !== null ? ['tid' => $tenantId] : [];
        $offset = ($page - 1) * $perPage;

        $projects = $this->connection->fetchAll(
            "SELECT p.*, t.name as tenant_name FROM projects p LEFT JOIN tenants t ON t.id = p.tenant_id {$where} ORDER BY p.created_at DESC LIMIT :l OFFSET :o",
            array_merge($params, ['l' => $perPage, 'o' => $offset]),
        );

        foreach ($projects as &$p) {
            $p['settings'] = json_decode($p['settings'] ?? '{}', true);
            $p['template_config'] = json_decode($p['template_config'] ?? '{}', true);
        }

        return $projects;
    }

    public function count(?int $tenantId = null): int
    {
        $where = $tenantId !== null ? 'WHERE tenant_id = :tid' : '';
        $params = $tenantId !== null ? ['tid' => $tenantId] : [];

        return (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM projects {$where}", $params);
    }

    public function update(int $id, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'type', 'status', 'dns_address', 'db_driver', 'db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'db_path'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }

        foreach (['settings', 'template_config', 'login_config'] as $jf) {
            if (array_key_exists($jf, $data)) {
                $sets[] = "{$jf} = :{$jf}";
                $params[$jf] = is_array($data[$jf]) ? json_encode($data[$jf]) : $data[$jf];
            }
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :now';
            $params['now'] = date('Y-m-d H:i:s');
            $this->connection->execute('UPDATE projects SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $this->connection->execute('DELETE FROM projects WHERE id = :id', ['id' => $id]);
    }

    private function getDefaultTemplateConfig(string $type): array
    {
        if ($type === 'website') {
            return [
                'cms_enabled' => true,
                'has_frontend' => true,
                'has_backend' => true,
                'default_plugins' => ['seo', 'carousel', 'gallery', 'contact-form'],
                'menu_positions' => ['header', 'footer', 'sidebar'],
            ];
        }

        return [
            'cms_enabled' => false,
            'has_frontend' => true,
            'has_backend' => true,
            'default_plugins' => [],
        ];
    }

    private function getDefaultLoginConfig(): array
    {
        return [
            'enabled' => true,
            'mfa_method' => 'email',
            'mfa_enabled' => true,
            'allow_registration' => false,
            'logo_url' => null,
            'background_color' => '#0f0f1a',
            'primary_color' => '#ff3e00',
            'layout' => 'centered',
        ];
    }

    private function slugify(string $text): string
    {
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', trim($text));
        } else {
            $text = strtolower(trim($text));
        }

        return trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    }
}
