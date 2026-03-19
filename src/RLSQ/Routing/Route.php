<?php

declare(strict_types=1);

namespace RLSQ\Routing;

class Route
{
    private string $path;
    /** @var string[] */
    private array $methods;
    private array $defaults;
    /** @var array<string, string> Contraintes regex par paramètre */
    private array $requirements;
    private ?string $compiledRegex = null;
    /** @var string[] Noms des paramètres extraits du path */
    private array $parameterNames = [];

    /**
     * @param string   $path         Le pattern d'URL (ex: /article/{id})
     * @param array    $defaults     Valeurs par défaut (_controller, paramètres optionnels)
     * @param string[] $methods      Méthodes HTTP autorisées (vide = toutes)
     * @param array    $requirements Contraintes regex par paramètre
     */
    public function __construct(
        string $path,
        array $defaults = [],
        array $methods = [],
        array $requirements = [],
    ) {
        $this->path = '/' . ltrim($path, '/');
        $this->defaults = $defaults;
        $this->methods = array_map('strtoupper', $methods);
        $this->requirements = $requirements;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = '/' . ltrim($path, '/');
        $this->compiledRegex = null;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function setMethods(array $methods): static
    {
        $this->methods = array_map('strtoupper', $methods);

        return $this;
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function getDefault(string $key): mixed
    {
        return $this->defaults[$key] ?? null;
    }

    public function setDefault(string $key, mixed $value): static
    {
        $this->defaults[$key] = $value;

        return $this;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getRequirement(string $key): ?string
    {
        return $this->requirements[$key] ?? null;
    }

    public function setRequirement(string $key, string $regex): static
    {
        $this->requirements[$key] = $regex;
        $this->compiledRegex = null;

        return $this;
    }

    public function getController(): ?string
    {
        return $this->defaults['_controller'] ?? null;
    }

    /**
     * Compile le path en regex pour le matching.
     * /article/{id} → #^/article/(?P<id>[^/]+)$#
     * Avec requirement 'id' => '\d+' → #^/article/(?P<id>\d+)$#
     */
    public function compile(): string
    {
        if ($this->compiledRegex !== null) {
            return $this->compiledRegex;
        }

        $this->parameterNames = [];

        $regex = preg_replace_callback(
            '#\{(\w+)\}#',
            function (array $matches): string {
                $name = $matches[1];
                $this->parameterNames[] = $name;
                $pattern = $this->requirements[$name] ?? '[^/]+';

                return '(?P<' . $name . '>' . $pattern . ')';
            },
            $this->path,
        );

        $this->compiledRegex = '#^' . $regex . '$#';

        return $this->compiledRegex;
    }

    /**
     * @return string[]
     */
    public function getParameterNames(): array
    {
        if ($this->compiledRegex === null) {
            $this->compile();
        }

        return $this->parameterNames;
    }

    /**
     * Vérifie si la méthode HTTP est autorisée pour cette route.
     */
    public function allowsMethod(string $method): bool
    {
        if (empty($this->methods)) {
            return true;
        }

        return in_array(strtoupper($method), $this->methods, true);
    }
}
