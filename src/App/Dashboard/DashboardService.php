<?php

declare(strict_types=1);

namespace App\Dashboard;

use RLSQ\Database\Connection;

class DashboardService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    // ==================== DASHBOARDS ====================

    /**
     * Retourne le dashboard par défaut pour un rôle donné.
     */
    public function getDefaultForRole(string $role): ?array
    {
        $dashboards = $this->connection->fetchAll('SELECT * FROM dashboards WHERE is_default = 1');

        foreach ($dashboards as $d) {
            $roles = json_decode($d['target_roles'] ?? '[]', true) ?: [];
            if (in_array($role, $roles, true)) {
                $d['widgets'] = $this->getWidgets((int) $d['id']);

                return $d;
            }
        }

        return null;
    }

    /**
     * Retourne un dashboard avec ses widgets.
     */
    public function getDashboard(int $id): ?array
    {
        $d = $this->connection->fetchOne('SELECT * FROM dashboards WHERE id = :id', ['id' => $id]);

        if (!$d) {
            return null;
        }

        $d['widgets'] = $this->getWidgets($id);
        $d['target_roles'] = json_decode($d['target_roles'] ?? '[]', true);

        return $d;
    }

    /**
     * @return array[]
     */
    public function getAllDashboards(): array
    {
        $dashboards = $this->connection->fetchAll('SELECT * FROM dashboards ORDER BY type, name');

        foreach ($dashboards as &$d) {
            $d['widgets'] = $this->getWidgets((int) $d['id']);
            $d['target_roles'] = json_decode($d['target_roles'] ?? '[]', true);
        }

        return $dashboards;
    }

    public function createDashboard(array $data): array
    {
        $this->connection->execute(
            'INSERT INTO dashboards (name, type, target_roles, layout, is_default) VALUES (:n, :t, :r, :l, :d)',
            [
                'n' => $data['name'] ?? 'Nouveau dashboard',
                't' => $data['type'] ?? 'custom',
                'r' => json_encode($data['target_roles'] ?? []),
                'l' => json_encode($data['layout'] ?? []),
                'd' => ($data['is_default'] ?? false) ? 1 : 0,
            ],
        );

        return $this->getDashboard((int) $this->connection->lastInsertId());
    }

    public function updateDashboard(int $id, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'type', 'is_default'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }

        if (array_key_exists('target_roles', $data)) {
            $sets[] = 'target_roles = :target_roles';
            $params['target_roles'] = json_encode($data['target_roles']);
        }

        if (array_key_exists('layout', $data)) {
            $sets[] = 'layout = :layout';
            $params['layout'] = json_encode($data['layout']);
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :now';
            $params['now'] = date('Y-m-d H:i:s');
            $this->connection->execute('UPDATE dashboards SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        return $this->getDashboard($id);
    }

    public function deleteDashboard(int $id): void
    {
        $this->connection->execute('DELETE FROM dashboards WHERE id = :id AND is_default = 0', ['id' => $id]);
    }

    // ==================== WIDGETS ====================

    /** @return array[] */
    public function getWidgets(int $dashboardId): array
    {
        $widgets = $this->connection->fetchAll(
            'SELECT * FROM dashboard_widgets WHERE dashboard_id = :did ORDER BY sort_order, position_y, position_x',
            ['did' => $dashboardId],
        );

        foreach ($widgets as &$w) {
            $w['config'] = json_decode($w['config'] ?? '{}', true);
        }

        return $widgets;
    }

    public function addWidget(int $dashboardId, array $data): array
    {
        $this->connection->execute(
            'INSERT INTO dashboard_widgets (dashboard_id, widget_type, title, config, position_x, position_y, width, height, sort_order)
             VALUES (:did, :wt, :t, :c, :px, :py, :w, :h, :so)',
            [
                'did' => $dashboardId,
                'wt' => $data['widget_type'] ?? 'counter',
                't' => $data['title'] ?? '',
                'c' => json_encode($data['config'] ?? []),
                'px' => $data['position_x'] ?? 0,
                'py' => $data['position_y'] ?? 0,
                'w' => $data['width'] ?? 1,
                'h' => $data['height'] ?? 1,
                'so' => $data['sort_order'] ?? 0,
            ],
        );

        $id = (int) $this->connection->lastInsertId();
        $w = $this->connection->fetchOne('SELECT * FROM dashboard_widgets WHERE id = :id', ['id' => $id]);
        $w['config'] = json_decode($w['config'] ?? '{}', true);

        return $w;
    }

    public function updateWidget(int $widgetId, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $widgetId];
        $allowed = ['widget_type', 'title', 'position_x', 'position_y', 'width', 'height', 'sort_order'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }

        if (array_key_exists('config', $data)) {
            $sets[] = 'config = :config';
            $params['config'] = json_encode($data['config']);
        }

        if (!empty($sets)) {
            $this->connection->execute('UPDATE dashboard_widgets SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        $w = $this->connection->fetchOne('SELECT * FROM dashboard_widgets WHERE id = :id', ['id' => $widgetId]);
        if (!$w) {
            return null;
        }

        $w['config'] = json_decode($w['config'] ?? '{}', true);

        return $w;
    }

    public function deleteWidget(int $widgetId): void
    {
        $this->connection->execute('DELETE FROM dashboard_widgets WHERE id = :id', ['id' => $widgetId]);
    }

    /**
     * Met à jour les positions de tous les widgets d'un dashboard en batch.
     */
    public function updateWidgetPositions(int $dashboardId, array $positions): void
    {
        foreach ($positions as $pos) {
            $this->connection->execute(
                'UPDATE dashboard_widgets SET position_x = :px, position_y = :py, width = :w, height = :h, sort_order = :so WHERE id = :id AND dashboard_id = :did',
                [
                    'id' => $pos['id'], 'did' => $dashboardId,
                    'px' => $pos['position_x'] ?? 0, 'py' => $pos['position_y'] ?? 0,
                    'w' => $pos['width'] ?? 1, 'h' => $pos['height'] ?? 1,
                    'so' => $pos['sort_order'] ?? 0,
                ],
            );
        }
    }

    // ==================== WIDGET DATA ====================

    /**
     * Résout les données d'un widget selon sa config.
     */
    public function resolveWidgetData(array $widget): mixed
    {
        $config = $widget['config'] ?? [];
        $source = $config['source'] ?? null;

        if ($source === null) {
            return $this->resolveStaticWidget($widget);
        }

        return match ($widget['widget_type']) {
            'counter' => $this->resolveCounter($source, $config),
            'datatable' => $this->resolveDataTable($source, $config),
            'chart' => $this->resolveChart($source, $config),
            'list' => $this->resolveList($source, $config),
            default => $config,
        };
    }

    private function resolveStaticWidget(array $widget): mixed
    {
        return $widget['config'] ?? [];
    }

    private function resolveCounter(string $source, array $config): array
    {
        $where = '';
        $params = [];

        if (!empty($config['filter'])) {
            $conditions = [];
            foreach ($config['filter'] as $k => $v) {
                $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $k);
                $conditions[] = "\"{$safe}\" = :f_{$safe}";
                $params["f_{$safe}"] = $v;
            }
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $count = (int) $this->connection->fetchColumn("SELECT COUNT(*) FROM \"{$source}\"{$where}", $params);

        return ['value' => $count, 'source' => $source];
    }

    private function resolveDataTable(string $source, array $config): array
    {
        $limit = $config['limit'] ?? 10;
        $sort = $config['sort'] ?? '-created_at';
        $columns = $config['columns'] ?? ['*'];

        $colStr = $columns === ['*'] ? '*' : '"' . implode('", "', $columns) . '"';
        $orderDir = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
        $orderCol = ltrim($sort, '-');

        $rows = $this->connection->fetchAll(
            "SELECT {$colStr} FROM \"{$source}\" ORDER BY \"{$orderCol}\" {$orderDir} LIMIT :lim",
            ['lim' => $limit],
        );

        return ['rows' => $rows, 'columns' => $columns, 'total' => count($rows)];
    }

    private function resolveChart(string $source, array $config): array
    {
        $groupBy = $config['group_by'] ?? 'status';
        $operation = $config['operation'] ?? 'count';
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $groupBy);

        $rows = $this->connection->fetchAll(
            "SELECT \"{$safe}\" as label, COUNT(*) as value FROM \"{$source}\" GROUP BY \"{$safe}\" ORDER BY value DESC LIMIT 10",
        );

        return ['labels' => array_column($rows, 'label'), 'values' => array_map('intval', array_column($rows, 'value'))];
    }

    private function resolveList(string $source, array $config): array
    {
        $limit = $config['limit'] ?? 5;
        $labelCol = $config['label_column'] ?? 'name';

        $rows = $this->connection->fetchAll(
            "SELECT * FROM \"{$source}\" ORDER BY created_at DESC LIMIT :lim",
            ['lim' => $limit],
        );

        return ['items' => $rows];
    }
}
