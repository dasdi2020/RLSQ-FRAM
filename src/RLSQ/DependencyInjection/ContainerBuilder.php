<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection;

use RLSQ\DependencyInjection\Compiler\Compiler;
use RLSQ\DependencyInjection\Compiler\CompilerPassInterface;
use RLSQ\DependencyInjection\Exception\CircularReferenceException;
use RLSQ\DependencyInjection\Exception\ParameterNotFoundException;
use RLSQ\DependencyInjection\Exception\ServiceNotFoundException;

class ContainerBuilder extends Container
{
    /** @var array<string, Definition> */
    private array $definitions = [];

    private Compiler $compiler;

    private bool $compiled = false;

    /** @var string[] Pile de résolution pour détecter les cycles */
    private array $resolutionStack = [];

    /** @var array<string, string> FQCN -> service id (pour l'autowiring) */
    private array $autowireMap = [];

    public function __construct()
    {
        parent::__construct();
        $this->compiler = new Compiler();
    }

    // --- Définitions ---

    /**
     * Enregistre un service via sa Definition.
     */
    public function register(string $id, ?string $class = null): Definition
    {
        $definition = new Definition($class ?? $id);
        $this->definitions[$id] = $definition;

        return $definition;
    }

    public function setDefinition(string $id, Definition $definition): Definition
    {
        $this->definitions[$id] = $definition;
        return $definition;
    }

    public function getDefinition(string $id): Definition
    {
        if (!isset($this->definitions[$id])) {
            throw new ServiceNotFoundException($id);
        }

        return $this->definitions[$id];
    }

    public function hasDefinition(string $id): bool
    {
        return isset($this->definitions[$id]);
    }

    public function removeDefinition(string $id): void
    {
        unset($this->definitions[$id]);
    }

    /**
     * @return array<string, Definition>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    // --- Get (lazy instantiation) ---

    public function get(string $id): mixed
    {
        // Alias
        $id = $this->aliases[$id] ?? $id;

        // Déjà instancié ?
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        // Pas de définition ?
        if (!isset($this->definitions[$id])) {
            throw new ServiceNotFoundException($id);
        }

        return $this->createService($id, $this->definitions[$id]);
    }

    public function has(string $id): bool
    {
        $id = $this->aliases[$id] ?? $id;

        return isset($this->services[$id]) || isset($this->definitions[$id]);
    }

    // --- Compiler ---

    public function addCompilerPass(CompilerPassInterface $pass): static
    {
        $this->compiler->addPass($pass);
        return $this;
    }

    public function compile(): void
    {
        // Construire le mapping FQCN -> id pour l'autowiring
        $this->buildAutowireMap();

        // Exécuter les passes de compilation
        $this->compiler->compile($this);

        // Reconstruire après les passes (elles peuvent ajouter des définitions)
        $this->buildAutowireMap();

        $this->compiled = true;
    }

    public function isCompiled(): bool
    {
        return $this->compiled;
    }

    // --- Tags ---

    /**
     * Retourne tous les service ids qui ont un tag donné.
     *
     * @return array<string, array<array<string, mixed>>> [serviceId => [tag attributes, ...]]
     */
    public function findTaggedServiceIds(string $tagName): array
    {
        $result = [];

        foreach ($this->definitions as $id => $definition) {
            if ($definition->hasTag($tagName)) {
                $result[$id] = $definition->getTag($tagName);
            }
        }

        return $result;
    }

    // --- Création de services ---

    private function createService(string $id, Definition $definition): mixed
    {
        // Détection de cycle
        if (in_array($id, $this->resolutionStack, true)) {
            throw new CircularReferenceException($id, $this->resolutionStack);
        }

        $this->resolutionStack[] = $id;

        try {
            $service = $this->instantiate($definition);

            // Appels de méthodes
            foreach ($definition->getMethodCalls() as $call) {
                $args = $this->resolveArguments($call['arguments']);
                $service->{$call['method']}(...$args);
            }

            // Stocker si partagé
            if ($definition->isShared()) {
                $this->services[$id] = $service;
            }

            return $service;
        } finally {
            array_pop($this->resolutionStack);
        }
    }

    private function instantiate(Definition $definition): object
    {
        // Factory ?
        $factory = $definition->getFactory();
        if ($factory !== null) {
            $args = $this->resolveArguments($definition->getArguments());
            return call_user_func_array($factory, $args);
        }

        $class = $definition->getClass();

        if ($class === null) {
            throw new \LogicException('La définition n\'a pas de classe.');
        }

        $arguments = $definition->getArguments();

        // Autowiring : si pas d'arguments définis, résoudre depuis le constructeur
        if (empty($arguments) && $definition->isAutowired()) {
            $arguments = $this->autowireArguments($class);
        } else {
            $arguments = $this->resolveArguments($arguments);
        }

        return new $class(...$arguments);
    }

    /**
     * Résout les arguments : Reference -> service, Parameter -> valeur, string %param% -> valeur.
     */
    private function resolveArguments(array $arguments): array
    {
        $resolved = [];

        foreach ($arguments as $arg) {
            $resolved[] = $this->resolveArgument($arg);
        }

        return $resolved;
    }

    private function resolveArgument(mixed $arg): mixed
    {
        if ($arg instanceof Reference) {
            return $this->get($arg->getId());
        }

        if ($arg instanceof Parameter) {
            return $this->getParameter($arg->getName());
        }

        // Résolution des %param% dans les strings
        if (is_string($arg) && preg_match('/^%(.+)%$/', $arg, $m)) {
            return $this->getParameter($m[1]);
        }

        if (is_array($arg)) {
            return $this->resolveArguments($arg);
        }

        return $arg;
    }

    /**
     * Résout les dépendances du constructeur par type-hints (autowiring).
     */
    private function autowireArguments(string $class): array
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('La classe "%s" n\'existe pas.', $class));
        }

        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            // Type-hint vers une classe/interface → chercher le service
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // Chercher par FQCN dans le mapping
                if (isset($this->autowireMap[$typeName])) {
                    $args[] = $this->get($this->autowireMap[$typeName]);
                    continue;
                }

                // Chercher un service enregistré directement par le FQCN
                if ($this->has($typeName)) {
                    $args[] = $this->get($typeName);
                    continue;
                }

                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }

                if ($param->allowsNull()) {
                    $args[] = null;
                    continue;
                }

                throw new \RuntimeException(sprintf(
                    'Impossible d\'autowirer le paramètre "$%s" (type "%s") pour le service "%s".',
                    $param->getName(),
                    $typeName,
                    $class,
                ));
            }

            // Type scalaire avec valeur par défaut
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Impossible d\'autowirer le paramètre "$%s" pour le service "%s" (pas de type-hint ni de valeur par défaut).',
                $param->getName(),
                $class,
            ));
        }

        return $args;
    }

    /**
     * Construit le mapping FQCN -> service id pour l'autowiring.
     */
    private function buildAutowireMap(): void
    {
        $this->autowireMap = [];

        foreach ($this->definitions as $id => $definition) {
            $class = $definition->getClass();
            if ($class === null || !class_exists($class)) {
                continue;
            }

            // Le service id lui-même
            $this->autowireMap[$class] = $id;

            // Toutes les interfaces implémentées
            $ref = new \ReflectionClass($class);
            foreach ($ref->getInterfaceNames() as $interface) {
                // N'enregistrer que si pas déjà pris (premier arrivé = priorité)
                if (!isset($this->autowireMap[$interface])) {
                    $this->autowireMap[$interface] = $id;
                }
            }
        }
    }
}
