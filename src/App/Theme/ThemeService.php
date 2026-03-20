<?php

declare(strict_types=1);

namespace App\Theme;

use RLSQ\Database\Connection;

class ThemeService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        $this->ensureTable();
    }

    public function getTheme(): array
    {
        $rows = $this->connection->fetchAll('SELECT * FROM tenant_theme LIMIT 1');
        if (empty($rows)) {
            return $this->getDefaults();
        }

        $theme = $rows[0];
        $theme['custom_css'] = $theme['custom_css'] ?? '';
        return $theme;
    }

    public function updateTheme(array $data): array
    {
        $current = $this->connection->fetchOne('SELECT id FROM tenant_theme LIMIT 1');
        $allowed = ['primary_color', 'secondary_color', 'accent_color', 'logo_url', 'favicon_url', 'font_family', 'custom_css', 'login_background_url', 'email_header_url'];

        if ($current) {
            $sets = [];
            $params = ['id' => $current['id']];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) { $sets[] = "{$f} = :{$f}"; $params[$f] = $data[$f]; }
            }
            if (!empty($sets)) {
                $sets[] = 'updated_at = :now'; $params['now'] = date('Y-m-d H:i:s');
                $this->connection->execute('UPDATE tenant_theme SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
            }
        } else {
            $cols = ['primary_color', 'secondary_color', 'accent_color'];
            $params = [];
            foreach ($cols as $c) { $params[$c] = $data[$c] ?? $this->getDefaults()[$c]; }
            $this->connection->execute('INSERT INTO tenant_theme (primary_color, secondary_color, accent_color) VALUES (:primary_color, :secondary_color, :accent_color)', $params);
        }

        return $this->getTheme();
    }

    public function generateCssVariables(): string
    {
        $t = $this->getTheme();
        return ":root {\n"
            . "  --color-primary: {$t['primary_color']};\n"
            . "  --color-secondary: {$t['secondary_color']};\n"
            . "  --color-accent: {$t['accent_color']};\n"
            . ($t['font_family'] ? "  --font-family: {$t['font_family']};\n" : '')
            . "}\n" . ($t['custom_css'] ?? '');
    }

    private function getDefaults(): array
    {
        return ['primary_color' => '#ff3e00', 'secondary_color' => '#1a1a2e', 'accent_color' => '#6cb2eb', 'logo_url' => null, 'favicon_url' => null, 'font_family' => '', 'custom_css' => '', 'login_background_url' => null, 'email_header_url' => null];
    }

    private function ensureTable(): void
    {
        $this->connection->exec('CREATE TABLE IF NOT EXISTS tenant_theme (id INTEGER PRIMARY KEY AUTOINCREMENT, primary_color VARCHAR(20) DEFAULT "#ff3e00", secondary_color VARCHAR(20) DEFAULT "#1a1a2e", accent_color VARCHAR(20) DEFAULT "#6cb2eb", logo_url VARCHAR(500), favicon_url VARCHAR(500), font_family VARCHAR(255), custom_css TEXT, login_background_url VARCHAR(500), email_header_url VARCHAR(500), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    }
}
