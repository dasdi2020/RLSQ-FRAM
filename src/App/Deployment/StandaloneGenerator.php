<?php

declare(strict_types=1);

namespace App\Deployment;

use App\PageBuilder\PageService;
use RLSQ\Database\Connection;

/**
 * Génère un projet standalone déployable pour un tenant.
 * Inclut : code PHP, composants Svelte, config, schema DB.
 */
class StandaloneGenerator
{
    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Génère le projet standalone dans un répertoire.
     *
     * @return array{output_dir: string, files_count: int}
     */
    public function generate(Connection $tenantConnection, array $tenantData, string $outputDir): array
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $filesCount = 0;

        // 1. composer.json
        $this->writeFile($outputDir . '/composer.json', json_encode([
            'name' => 'rlsq/' . ($tenantData['slug'] ?? 'app'),
            'description' => 'Application générée par RLSQ-FRAM pour ' . ($tenantData['name'] ?? 'tenant'),
            'require' => ['php' => '>=8.2'],
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $filesCount++;

        // 2. .env
        $this->writeFile($outputDir . '/.env', implode("\n", [
            'APP_ENV=production', 'APP_DEBUG=false',
            'APP_SECRET=' . bin2hex(random_bytes(16)),
            'DATABASE_DRIVER=sqlite', 'DATABASE_PATH=var/db.sqlite',
        ]));
        $filesCount++;

        // 3. public/index.php
        $this->ensureDir($outputDir . '/public');
        $this->writeFile($outputDir . '/public/index.php', $this->generateIndexPhp($tenantData));
        $filesCount++;

        // 4. Exporter le schema SQL
        $this->ensureDir($outputDir . '/var');
        $schema = $this->exportSchema($tenantConnection);
        $this->writeFile($outputDir . '/var/schema.sql', $schema);
        $filesCount++;

        // 5. Exporter les données
        $dataExport = $this->exportData($tenantConnection);
        $this->writeFile($outputDir . '/var/data.json', json_encode($dataExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $filesCount++;

        // 6. Générer les pages Svelte
        $pageService = new PageService($tenantConnection);
        $pages = $pageService->getAllPages();

        if (!empty($pages)) {
            $this->ensureDir($outputDir . '/frontend/src/routes');
            foreach ($pages as $page) {
                $fullPage = $pageService->getPage((int) $page['id']);
                if ($fullPage) {
                    $svelte = $pageService->generateSvelteCode((int) $page['id']);
                    if ($svelte) {
                        $filename = $this->slugToFilename($page['slug']) . '.svelte';
                        $this->writeFile($outputDir . '/frontend/src/routes/' . $filename, $svelte);
                        $filesCount++;
                    }
                }
            }
        }

        // 7. Config du tenant
        $this->writeFile($outputDir . '/config/tenant.json', json_encode([
            'name' => $tenantData['name'] ?? '',
            'slug' => $tenantData['slug'] ?? '',
            'generated_at' => date('Y-m-d H:i:s'),
            'generator' => 'RLSQ-FRAM v0.1.0',
            'pages' => count($pages),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $filesCount++;

        // 8. README
        $this->writeFile($outputDir . '/README.md', $this->generateReadme($tenantData));
        $filesCount++;

        return ['output_dir' => $outputDir, 'files_count' => $filesCount];
    }

    private function generateIndexPhp(array $tenant): string
    {
        $name = addslashes($tenant['name'] ?? 'App');

        return <<<'PHP'
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/vendor/autoload.php';
// Application standalone générée par RLSQ-FRAM
echo '<h1>PHP_NAME</h1><p>Application en production.</p>';
PHP;
    }

    private function exportSchema(Connection $c): string
    {
        $tables = $c->fetchAll("SELECT sql FROM sqlite_master WHERE type='table' AND sql IS NOT NULL ORDER BY name");
        $sql = "-- Schema exporté par RLSQ-FRAM\n-- " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $t) {
            $sql .= $t['sql'] . ";\n\n";
        }

        return $sql;
    }

    private function exportData(Connection $c): array
    {
        $tables = $c->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE '_%' ORDER BY name");
        $data = [];

        foreach ($tables as $t) {
            $name = $t['name'];
            try {
                $data[$name] = $c->fetchAll("SELECT * FROM \"{$name}\"");
            } catch (\Throwable) {
                $data[$name] = [];
            }
        }

        return $data;
    }

    private function generateReadme(array $tenant): string
    {
        $name = $tenant['name'] ?? 'Application';

        return <<<MD
# {$name}

Application générée par **RLSQ-FRAM** le {$this->now()}.

## Installation

```bash
composer install
php -S localhost:8000 -t public
```

## Structure

- `public/` — Point d'entrée HTTP
- `frontend/` — Composants Svelte générés
- `var/schema.sql` — Schema de la base de données
- `var/data.json` — Données exportées
- `config/tenant.json` — Configuration du tenant
MD;
    }

    private function slugToFilename(string $slug): string
    {
        return str_replace('-', '_', ucfirst($slug));
    }

    private function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
