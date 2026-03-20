<?php

declare(strict_types=1);

namespace App\PageBuilder;

use RLSQ\Database\Connection;

class PageService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    // ==================== PAGES ====================

    public function createPage(array $data): array
    {
        $name = $data['name'] ?? throw new \InvalidArgumentException('name requis.');
        $slug = $data['slug'] ?? $this->slugify($name);

        $this->connection->execute(
            'INSERT INTO pages (name, slug, route_path, meta_title, meta_description, access_roles, parent_id)
             VALUES (:n, :s, :rp, :mt, :md, :ar, :pid)',
            [
                'n' => $name, 's' => $slug,
                'rp' => $data['route_path'] ?? '/' . $slug,
                'mt' => $data['meta_title'] ?? $name,
                'md' => $data['meta_description'] ?? null,
                'ar' => json_encode($data['access_roles'] ?? []),
                'pid' => $data['parent_id'] ?? null,
            ],
        );

        return $this->getPage((int) $this->connection->lastInsertId());
    }

    public function getPage(int $id): ?array
    {
        $page = $this->connection->fetchOne('SELECT * FROM pages WHERE id = :id', ['id' => $id]);
        if (!$page) {
            return null;
        }

        $page['access_roles'] = json_decode($page['access_roles'] ?? '[]', true);
        $page['components'] = $this->getComponents($id);

        return $page;
    }

    public function getPageBySlug(string $slug): ?array
    {
        $page = $this->connection->fetchOne('SELECT * FROM pages WHERE slug = :s', ['s' => $slug]);
        if (!$page) {
            return null;
        }

        $page['access_roles'] = json_decode($page['access_roles'] ?? '[]', true);
        $page['components'] = $this->getComponents((int) $page['id']);

        return $page;
    }

    /** @return array[] */
    public function getAllPages(): array
    {
        $pages = $this->connection->fetchAll('SELECT * FROM pages ORDER BY name');

        foreach ($pages as &$p) {
            $p['access_roles'] = json_decode($p['access_roles'] ?? '[]', true);
            $p['component_count'] = (int) $this->connection->fetchColumn(
                'SELECT COUNT(*) FROM page_components WHERE page_id = :pid',
                ['pid' => $p['id']],
            );
        }

        return $pages;
    }

    public function updatePage(int $id, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'route_path', 'is_published', 'meta_title', 'meta_description', 'parent_id', 'version'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }

        if (array_key_exists('access_roles', $data)) {
            $sets[] = 'access_roles = :access_roles';
            $params['access_roles'] = json_encode($data['access_roles']);
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :now';
            $params['now'] = date('Y-m-d H:i:s');
            $this->connection->execute('UPDATE pages SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        return $this->getPage($id);
    }

    public function deletePage(int $id): void
    {
        $this->connection->execute('DELETE FROM pages WHERE id = :id', ['id' => $id]);
    }

    public function duplicatePage(int $id): ?array
    {
        $source = $this->getPage($id);
        if (!$source) {
            return null;
        }

        $copy = $this->createPage([
            'name' => $source['name'] . ' (copie)',
            'slug' => $source['slug'] . '-copy-' . time(),
            'route_path' => $source['route_path'] . '-copy',
            'meta_title' => $source['meta_title'],
            'access_roles' => $source['access_roles'],
        ]);

        // Dupliquer les composants
        foreach ($source['components'] as $comp) {
            $this->addComponent((int) $copy['id'], [
                'type' => $comp['type'],
                'props' => $comp['props'],
                'styles' => $comp['styles'],
                'content' => $comp['content'],
                'width' => $comp['width'],
                'height' => $comp['height'],
                'position_x' => $comp['position_x'],
                'position_y' => $comp['position_y'],
                'sort_order' => $comp['sort_order'],
            ]);
        }

        return $this->getPage((int) $copy['id']);
    }

    // ==================== COMPONENTS ====================

    /** @return array[] */
    public function getComponents(int $pageId): array
    {
        $comps = $this->connection->fetchAll(
            'SELECT * FROM page_components WHERE page_id = :pid ORDER BY sort_order, position_y, position_x',
            ['pid' => $pageId],
        );

        foreach ($comps as &$c) {
            $c['props'] = json_decode($c['props'] ?? '{}', true);
            $c['styles'] = json_decode($c['styles'] ?? '{}', true);
            $c['children_ids'] = json_decode($c['children_ids'] ?? '[]', true);
        }

        return $comps;
    }

    public function addComponent(int $pageId, array $data): array
    {
        $this->connection->execute(
            'INSERT INTO page_components (page_id, type, props, styles, content, children_ids, position_x, position_y, width, height, sort_order, parent_component_id)
             VALUES (:pid, :t, :p, :s, :c, :ci, :px, :py, :w, :h, :so, :pcid)',
            [
                'pid' => $pageId,
                't' => $data['type'] ?? 'text',
                'p' => json_encode($data['props'] ?? []),
                's' => json_encode($data['styles'] ?? []),
                'c' => $data['content'] ?? '',
                'ci' => json_encode($data['children_ids'] ?? []),
                'px' => $data['position_x'] ?? 0,
                'py' => $data['position_y'] ?? 0,
                'w' => $data['width'] ?? 12,
                'h' => $data['height'] ?? 1,
                'so' => $data['sort_order'] ?? 0,
                'pcid' => $data['parent_component_id'] ?? null,
            ],
        );

        $id = (int) $this->connection->lastInsertId();
        $c = $this->connection->fetchOne('SELECT * FROM page_components WHERE id = :id', ['id' => $id]);
        $c['props'] = json_decode($c['props'] ?? '{}', true);
        $c['styles'] = json_decode($c['styles'] ?? '{}', true);

        return $c;
    }

    public function updateComponent(int $componentId, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $componentId];
        $allowed = ['type', 'content', 'position_x', 'position_y', 'width', 'height', 'sort_order', 'parent_component_id'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }

        foreach (['props', 'styles', 'children_ids'] as $jf) {
            if (array_key_exists($jf, $data)) {
                $sets[] = "{$jf} = :{$jf}";
                $params[$jf] = json_encode($data[$jf]);
            }
        }

        if (!empty($sets)) {
            $this->connection->execute('UPDATE page_components SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        $c = $this->connection->fetchOne('SELECT * FROM page_components WHERE id = :id', ['id' => $componentId]);
        if (!$c) {
            return null;
        }

        $c['props'] = json_decode($c['props'] ?? '{}', true);
        $c['styles'] = json_decode($c['styles'] ?? '{}', true);

        return $c;
    }

    public function deleteComponent(int $componentId): void
    {
        $this->connection->execute('DELETE FROM page_components WHERE id = :id', ['id' => $componentId]);
    }

    public function updateComponentPositions(int $pageId, array $positions): void
    {
        foreach ($positions as $pos) {
            $this->connection->execute(
                'UPDATE page_components SET position_x = :px, position_y = :py, width = :w, height = :h, sort_order = :so WHERE id = :id AND page_id = :pid',
                [
                    'id' => $pos['id'], 'pid' => $pageId,
                    'px' => $pos['position_x'] ?? 0, 'py' => $pos['position_y'] ?? 0,
                    'w' => $pos['width'] ?? 12, 'h' => $pos['height'] ?? 1,
                    'so' => $pos['sort_order'] ?? 0,
                ],
            );
        }
    }

    // ==================== RENDER ====================

    /**
     * Génère le HTML complet d'une page pour le preview.
     */
    public function renderPage(int $pageId): ?string
    {
        $page = $this->getPage($pageId);
        if (!$page) {
            return null;
        }

        $title = htmlspecialchars($page['meta_title'] ?? $page['name'], ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars($page['meta_description'] ?? '', ENT_QUOTES, 'UTF-8');
        $componentsHtml = $this->renderComponents($page['components']);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
            <meta name="description" content="{$desc}">
            <style>
                * { margin:0; padding:0; box-sizing:border-box; }
                body { font-family:system-ui,-apple-system,sans-serif; color:#333; }
                .page-grid { display:grid; grid-template-columns:repeat(12,1fr); gap:16px; max-width:1200px; margin:0 auto; padding:24px; }
                .component { padding:16px; }
                img { max-width:100%; height:auto; }
                .btn { display:inline-block; padding:10px 24px; border-radius:6px; text-decoration:none; font-weight:600; cursor:pointer; }
                .divider { border-top:1px solid #e0e0e0; margin:16px 0; }
            </style>
        </head>
        <body>
            <div class="page-grid">{$componentsHtml}</div>
        </body>
        </html>
        HTML;
    }

    /**
     * Génère le code Svelte d'une page (pour déploiement standalone).
     */
    public function generateSvelteCode(int $pageId): ?string
    {
        $page = $this->getPage($pageId);
        if (!$page) {
            return null;
        }

        $components = $page['components'];
        $svelteBody = '';

        foreach ($components as $comp) {
            $svelteBody .= $this->componentToSvelte($comp);
        }

        $title = htmlspecialchars($page['meta_title'] ?? $page['name'], ENT_QUOTES, 'UTF-8');

        return <<<SVELTE
        <script>
            // Page: {$page['name']} — Generated by RLSQ-FRAM
        </script>

        <svelte:head>
            <title>{$title}</title>
        </svelte:head>

        <div class="page-grid">
            {$svelteBody}
        </div>

        <style>
            .page-grid { display:grid; grid-template-columns:repeat(12,1fr); gap:16px; max-width:1200px; margin:0 auto; padding:24px; }
        </style>
        SVELTE;
    }

    // ==================== PRIVATE ====================

    private function renderComponents(array $components): string
    {
        $html = '';

        foreach ($components as $comp) {
            $w = (int) ($comp['width'] ?? 12);
            $styles = $comp['styles'] ?? [];
            $inlineStyle = $this->buildInlineStyle($styles);
            $spanStyle = "grid-column:span {$w};{$inlineStyle}";

            $html .= "<div class=\"component\" style=\"{$spanStyle}\">";
            $html .= $this->renderComponent($comp);
            $html .= "</div>\n";
        }

        return $html;
    }

    private function renderComponent(array $comp): string
    {
        $props = $comp['props'] ?? [];
        $content = $comp['content'] ?? '';
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return match ($comp['type']) {
            'heading' => sprintf('<h%d>%s</h%1$d>', $props['level'] ?? 2, $e($content)),
            'text' => '<p>' . nl2br($e($content)) . '</p>',
            'richtext' => $content, // Already HTML
            'image' => sprintf('<img src="%s" alt="%s" />', $e($props['src'] ?? ''), $e($props['alt'] ?? '')),
            'button' => sprintf('<a class="btn" href="%s" style="background:%s;color:%s;">%s</a>',
                $e($props['url'] ?? '#'), $e($props['bg'] ?? '#ff3e00'), $e($props['color'] ?? '#fff'), $e($content)),
            'divider' => '<div class="divider"></div>',
            'spacer' => sprintf('<div style="height:%dpx;"></div>', $props['height'] ?? 32),
            'card' => sprintf('<div style="border:1px solid #e0e0e0;border-radius:8px;padding:16px;">%s</div>', $content ? nl2br($e($content)) : ''),
            'html' => $content, // Custom raw HTML
            'form' => sprintf('<div data-form-slug="%s">[Formulaire : %s]</div>', $e($props['form_slug'] ?? ''), $e($props['form_slug'] ?? '')),
            'datatable' => sprintf('<div data-table="%s">[Table : %s]</div>', $e($props['table'] ?? ''), $e($props['table'] ?? '')),
            'iframe' => sprintf('<iframe src="%s" width="100%%" height="%d" frameborder="0"></iframe>', $e($props['src'] ?? ''), $props['height'] ?? 400),
            default => '<div>' . $e($content) . '</div>',
        };
    }

    private function componentToSvelte(array $comp): string
    {
        $w = (int) ($comp['width'] ?? 12);
        $content = $comp['content'] ?? '';
        $props = $comp['props'] ?? [];

        $inner = match ($comp['type']) {
            'heading' => sprintf('<h%d>%s</h%1$d>', $props['level'] ?? 2, htmlspecialchars($content, ENT_QUOTES, 'UTF-8')),
            'text' => '<p>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</p>',
            'image' => '<img src="' . ($props['src'] ?? '') . '" alt="' . ($props['alt'] ?? '') . '" />',
            'button' => '<button>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</button>',
            'divider' => '<hr />',
            'spacer' => '<div style="height:' . ($props['height'] ?? 32) . 'px"></div>',
            default => '<div>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</div>',
        };

        return "    <div style=\"grid-column:span {$w}\">\n        {$inner}\n    </div>\n";
    }

    private function buildInlineStyle(array $styles): string
    {
        $css = '';
        $map = ['backgroundColor' => 'background-color', 'color' => 'color', 'padding' => 'padding',
            'margin' => 'margin', 'borderRadius' => 'border-radius', 'fontSize' => 'font-size', 'textAlign' => 'text-align'];

        foreach ($map as $prop => $cssProp) {
            if (!empty($styles[$prop])) {
                $css .= "{$cssProp}:{$styles[$prop]};";
            }
        }

        return $css;
    }

    private function slugify(string $t): string
    {
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', trim($t));
        } else {
            $t = strtolower(trim($t));
        }

        return trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
    }
}
