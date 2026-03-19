<?php

declare(strict_types=1);

namespace RLSQ\Config;

/**
 * Cache la configuration compilée dans un fichier PHP pour la performance.
 */
class ConfigCache
{
    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $debug = false,
    ) {}

    /**
     * Retourne le chemin du fichier cache pour une clé donnée.
     */
    public function getPath(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . $key . '.php';
    }

    /**
     * Vérifie si le cache est valide.
     * En mode debug, compare les dates de modification des sources.
     *
     * @param string[] $sourceFiles Fichiers sources dont le cache dépend
     */
    public function isFresh(string $key, array $sourceFiles = []): bool
    {
        $cachePath = $this->getPath($key);

        if (!file_exists($cachePath)) {
            return false;
        }

        if (!$this->debug) {
            return true;
        }

        $cacheTime = filemtime($cachePath);

        foreach ($sourceFiles as $file) {
            if (file_exists($file) && filemtime($file) > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Écrit les données dans le cache.
     */
    public function write(string $key, mixed $data): void
    {
        $path = $this->getPath($key);

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $content = '<?php return ' . var_export($data, true) . ';' . "\n";

        file_put_contents($path, $content, LOCK_EX);
    }

    /**
     * Lit les données depuis le cache.
     */
    public function read(string $key): mixed
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return null;
        }

        return require $path;
    }
}
