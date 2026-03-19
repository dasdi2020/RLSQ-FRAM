<?php

declare(strict_types=1);

namespace RLSQ\Templating\Loader;

class FilesystemLoader
{
    /** @var string[] */
    private array $paths;

    /**
     * @param string|string[] $paths
     */
    public function __construct(string|array $paths)
    {
        $this->paths = is_string($paths) ? [$paths] : $paths;
    }

    public function getSource(string $name): string
    {
        $path = $this->find($name);

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Impossible de lire le template "%s".', $name));
        }

        return $content;
    }

    public function exists(string $name): bool
    {
        try {
            $this->find($name);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function find(string $name): string
    {
        foreach ($this->paths as $basePath) {
            $fullPath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $name;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        throw new \RuntimeException(sprintf('Le template "%s" est introuvable.', $name));
    }
}
