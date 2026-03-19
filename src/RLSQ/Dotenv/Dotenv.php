<?php

declare(strict_types=1);

namespace RLSQ\Dotenv;

/**
 * Charge les variables d'environnement depuis des fichiers .env
 *
 * Ordre de chargement (le dernier gagne) :
 *   .env → .env.local → .env.{APP_ENV} → .env.{APP_ENV}.local
 *
 * Syntaxe supportée :
 *   KEY=value
 *   KEY="value with spaces"
 *   KEY='value with spaces'
 *   KEY=${OTHER_KEY}/suffix   (interpolation)
 *   # commentaire
 *   export KEY=value
 */
class Dotenv
{
    /** @var array<string, string> Variables chargées */
    private array $values = [];

    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');
    }

    /**
     * Charge les fichiers .env dans l'ordre de priorité.
     */
    public function load(): void
    {
        // 1. .env (base)
        $this->loadFile($this->directory . '/.env');

        // 2. .env.local (overrides locaux, gitignored)
        $this->loadFile($this->directory . '/.env.local');

        // 3. .env.{APP_ENV} (spécifique à l'environnement)
        $env = $this->get('APP_ENV', 'dev');
        $this->loadFile($this->directory . '/.env.' . $env);

        // 4. .env.{APP_ENV}.local (overrides locaux par env)
        $this->loadFile($this->directory . '/.env.' . $env . '.local');
    }

    /**
     * Charge un seul fichier .env.
     */
    public function loadFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignorer les commentaires et lignes vides
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Supporter "export KEY=value"
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Retirer les guillemets
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Retirer les commentaires inline (sauf entre guillemets)
            if (!str_starts_with($value, '"') && !str_starts_with($value, "'")) {
                $hashPos = strpos($value, ' #');
                if ($hashPos !== false) {
                    $value = trim(substr($value, 0, $hashPos));
                }
            }

            // Interpolation ${VAR}
            $value = preg_replace_callback('/\$\{(\w+)\}/', function (array $m) {
                return $this->get($m[1], '');
            }, $value);

            $this->values[$key] = $value;

            // Pousser dans $_ENV et $_SERVER (comme Symfony)
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;

            // putenv pour getenv()
            putenv("{$key}={$value}");
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]) || isset($_ENV[$key]) || isset($_SERVER[$key]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Raccourci statique pour charger depuis un répertoire.
     */
    public static function loadIn(string $directory): static
    {
        $dotenv = new static($directory);
        $dotenv->load();

        return $dotenv;
    }
}
