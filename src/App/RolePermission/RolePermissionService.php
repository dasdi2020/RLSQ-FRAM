<?php

declare(strict_types=1);

namespace App\RolePermission;

use RLSQ\Database\Connection;

class RolePermissionService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        $this->ensureTable();
    }

    public function createRole(string $name, string $slug, array $permissions = [], ?string $description = null): array
    {
        $this->connection->execute('INSERT INTO custom_roles (name, slug, description, permissions) VALUES (:n, :s, :d, :p)',
            ['n' => $name, 's' => $slug, 'd' => $description, 'p' => json_encode($permissions)]);
        return $this->getRole((int) $this->connection->lastInsertId());
    }

    public function getRole(int $id): ?array
    {
        $r = $this->connection->fetchOne('SELECT * FROM custom_roles WHERE id = :id', ['id' => $id]);
        if ($r) { $r['permissions'] = json_decode($r['permissions'] ?? '[]', true); }
        return $r ?: null;
    }

    /** @return array[] */
    public function getAllRoles(): array
    {
        $roles = $this->connection->fetchAll('SELECT * FROM custom_roles ORDER BY name');
        foreach ($roles as &$r) { $r['permissions'] = json_decode($r['permissions'] ?? '[]', true); }
        return $roles;
    }

    public function updateRole(int $id, array $data): ?array
    {
        $sets = []; $params = ['id' => $id];
        foreach (['name', 'description'] as $f) { if (array_key_exists($f, $data)) { $sets[] = "{$f} = :{$f}"; $params[$f] = $data[$f]; } }
        if (array_key_exists('permissions', $data)) { $sets[] = 'permissions = :permissions'; $params['permissions'] = json_encode($data['permissions']); }
        if (!empty($sets)) { $this->connection->execute('UPDATE custom_roles SET ' . implode(', ', $sets) . ' WHERE id = :id', $params); }
        return $this->getRole($id);
    }

    public function deleteRole(int $id): void
    {
        $this->connection->execute('DELETE FROM custom_roles WHERE id = :id', ['id' => $id]);
    }

    public function hasPermission(array $role, string $permission): bool
    {
        return in_array($permission, $role['permissions'] ?? [], true);
    }

    /** @return string[] */
    public function getAvailablePermissions(): array
    {
        return [
            'members.view', 'members.create', 'members.edit', 'members.delete',
            'clubs.view', 'clubs.create', 'clubs.edit', 'clubs.delete',
            'formations.view', 'formations.create', 'formations.edit', 'formations.delete', 'formations.manage_registrations',
            'activities.view', 'activities.create', 'activities.edit', 'activities.delete',
            'payments.view', 'payments.refund', 'payments.configure',
            'forms.view', 'forms.create', 'forms.edit', 'forms.delete', 'forms.view_submissions',
            'pages.view', 'pages.create', 'pages.edit', 'pages.delete', 'pages.publish',
            'settings.view', 'settings.edit', 'plugins.manage', 'schema.manage',
            'audit.view', 'export.data', 'import.data',
        ];
    }

    private function ensureTable(): void
    {
        $this->connection->exec('CREATE TABLE IF NOT EXISTS custom_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE, description TEXT, permissions TEXT DEFAULT "[]", created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    }
}
