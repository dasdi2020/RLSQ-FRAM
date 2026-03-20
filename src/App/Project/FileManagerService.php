<?php

declare(strict_types=1);

namespace App\Project;

/**
 * Gère les fichiers d'un projet dans un sandbox isolé (var/projects/{slug}/files/).
 */
class FileManagerService
{
    private string $basePath;

    public function __construct(string $projectDir, string $projectSlug)
    {
        $this->basePath = $projectDir . '/var/projects/' . $projectSlug . '/files';

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0777, true);
            $this->seedDefaultFiles();
        }
    }

    /**
     * Liste l'arborescence des fichiers.
     */
    public function listTree(string $path = ''): array
    {
        $fullPath = $this->resolve($path);
        if (!is_dir($fullPath)) {
            return [];
        }

        $items = [];
        $entries = scandir($fullPath);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path ? "{$path}/{$entry}" : $entry;
            $fullEntryPath = "{$fullPath}/{$entry}";

            if (is_dir($fullEntryPath)) {
                $items[] = [
                    'name' => $entry,
                    'path' => $entryPath,
                    'type' => 'directory',
                    'children' => $this->listTree($entryPath),
                ];
            } else {
                $items[] = [
                    'name' => $entry,
                    'path' => $entryPath,
                    'type' => 'file',
                    'size' => filesize($fullEntryPath),
                    'extension' => pathinfo($entry, PATHINFO_EXTENSION),
                    'modified' => date('Y-m-d H:i:s', filemtime($fullEntryPath)),
                ];
            }
        }

        // Dossiers d'abord, puis fichiers
        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    /**
     * Lit le contenu d'un fichier.
     */
    public function read(string $path): ?string
    {
        $fullPath = $this->resolve($path);

        if (!file_exists($fullPath) || is_dir($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    /**
     * Écrit le contenu d'un fichier (crée ou met à jour).
     */
    public function write(string $path, string $content): void
    {
        $fullPath = $this->resolve($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);
    }

    /**
     * Crée un dossier.
     */
    public function createDirectory(string $path): void
    {
        $fullPath = $this->resolve($path);

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }
    }

    /**
     * Supprime un fichier ou dossier.
     */
    public function delete(string $path): void
    {
        $fullPath = $this->resolve($path);

        if (is_dir($fullPath)) {
            $this->removeDir($fullPath);
        } elseif (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Renomme un fichier/dossier.
     */
    public function rename(string $oldPath, string $newName): void
    {
        $fullOld = $this->resolve($oldPath);
        $dir = dirname($fullOld);
        $fullNew = $dir . '/' . basename($newName);

        if (file_exists($fullOld)) {
            rename($fullOld, $fullNew);
        }
    }

    /**
     * Détermine le langage Monaco depuis l'extension.
     */
    public static function getLanguage(string $extension): string
    {
        return match (strtolower($extension)) {
            'php' => 'php',
            'js', 'mjs' => 'javascript',
            'ts' => 'typescript',
            'html', 'htm' => 'html',
            'css' => 'css',
            'scss', 'sass' => 'scss',
            'json' => 'json',
            'yaml', 'yml' => 'yaml',
            'xml' => 'xml',
            'md' => 'markdown',
            'sql' => 'sql',
            'svelte' => 'html',
            'twig' => 'html',
            default => 'plaintext',
        };
    }

    private function resolve(string $path): string
    {
        // Sécurité : empêcher le path traversal
        $path = str_replace(['..', "\0"], '', $path);
        $path = ltrim($path, '/\\');

        return $this->basePath . '/' . $path;
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function seedDefaultFiles(): void
    {
        // Créer une structure de fichiers par défaut
        $this->write('index.html', "<!DOCTYPE html>\n<html lang=\"fr\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>Mon Site</title>\n    <link rel=\"stylesheet\" href=\"css/style.css\">\n</head>\n<body>\n    <h1>Bienvenue</h1>\n    <p>Votre site est prêt.</p>\n    <script src=\"js/app.js\"></script>\n</body>\n</html>");
        $this->write('css/style.css', "/* Styles du site */\n* {\n    margin: 0;\n    padding: 0;\n    box-sizing: border-box;\n}\n\nbody {\n    font-family: system-ui, -apple-system, sans-serif;\n    line-height: 1.6;\n    color: #333;\n}\n\nh1 {\n    padding: 2rem;\n    text-align: center;\n}");
        $this->write('js/app.js', "// JavaScript du site\nconsole.log('Site chargé.');\n\ndocument.addEventListener('DOMContentLoaded', () => {\n    // Votre code ici\n});");
        $this->write('api/index.php', "<?php\n\ndeclare(strict_types=1);\n\n// API endpoint\nheader('Content-Type: application/json');\n\necho json_encode([\n    'status' => 'ok',\n    'message' => 'API ready',\n]);");
    }
}
