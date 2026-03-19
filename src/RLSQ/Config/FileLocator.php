<?php

declare(strict_types=1);

namespace RLSQ\Config;

use RLSQ\Config\Exception\FileNotFoundException;

/**
 * Localise un fichier dans un ou plusieurs répertoires.
 */
class FileLocator
{
    /** @var string[] */
    private array $paths;

    /**
     * @param string|string[] $paths
     */
    public function __construct(string|array $paths = [])
    {
        $this->paths = is_string($paths) ? [$paths] : $paths;
    }

    /**
     * Retourne le chemin absolu vers le fichier.
     */
    public function locate(string $name): string
    {
        // Chemin absolu direct
        if ($this->isAbsolutePath($name)) {
            if (!file_exists($name)) {
                throw new FileNotFoundException($name);
            }
            return $name;
        }

        // Chercher dans les répertoires configurés
        foreach ($this->paths as $path) {
            $fullPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $name;

            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        throw new FileNotFoundException($name);
    }

    private function isAbsolutePath(string $path): bool
    {
        // Unix absolute ou Windows absolute (C:\... ou \\...)
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || (strlen($path) > 2 && $path[1] === ':');
    }
}
