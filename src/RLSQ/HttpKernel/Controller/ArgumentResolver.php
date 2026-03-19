<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpKernel\Controller\ValueResolver\DefaultValueResolver;
use RLSQ\HttpKernel\Controller\ValueResolver\RequestValueResolver;
use RLSQ\HttpKernel\Controller\ValueResolver\RouteParameterValueResolver;
use RLSQ\HttpKernel\Controller\ValueResolver\ValueResolverInterface;

/**
 * Résout les arguments d'un contrôleur via une chaîne de ValueResolvers.
 *
 * Ordre par défaut :
 *   1. RequestValueResolver     — Injecte Request
 *   2. (ValueResolvers custom)  — EntityValueResolver, ServiceValueResolver, etc.
 *   3. RouteParameterValueResolver — Paramètres de route (string, int, float, bool)
 *   4. DefaultValueResolver     — Valeurs par défaut et null
 *
 * Les resolvers custom (Entity, Service) sont insérés AVANT RouteParameter
 * pour qu'un type-hint `Article $article` soit résolu en entité
 * plutôt qu'en string depuis les attributs de route.
 */
class ArgumentResolver implements ArgumentResolverInterface
{
    /** @var ValueResolverInterface[] */
    private array $resolvers;

    /**
     * @param ValueResolverInterface[] $additionalResolvers Resolvers custom (Entity, Service, etc.)
     */
    public function __construct(array $additionalResolvers = [])
    {
        $this->resolvers = array_merge(
            [new RequestValueResolver()],
            $additionalResolvers,
            [new RouteParameterValueResolver()],
            [new DefaultValueResolver()],
        );
    }

    /**
     * Ajoute un resolver custom. Il sera inséré avant RouteParameter et Default.
     */
    public function addResolver(ValueResolverInterface $resolver): void
    {
        // Insérer avant les 2 derniers (RouteParameter + Default)
        array_splice($this->resolvers, -2, 0, [$resolver]);
    }

    public function getArguments(Request $request, callable $controller): array
    {
        $reflection = $this->getReflection($controller);
        $arguments = [];

        foreach ($reflection->getParameters() as $param) {
            $resolved = false;

            foreach ($this->resolvers as $resolver) {
                $result = $resolver->resolve($request, $param);

                if (!empty($result)) {
                    $arguments[] = $result[0];
                    $resolved = true;
                    break;
                }
            }

            if (!$resolved) {
                throw new \RuntimeException(sprintf(
                    'Impossible de résoudre l\'argument "$%s" (type: %s) du contrôleur. '
                    . 'Aucun ValueResolver ne peut le fournir.',
                    $param->getName(),
                    $param->getType() instanceof \ReflectionNamedType ? $param->getType()->getName() : 'mixed',
                ));
            }
        }

        return $arguments;
    }

    private function getReflection(callable $controller): \ReflectionFunctionAbstract
    {
        if (is_array($controller)) {
            return new \ReflectionMethod($controller[0], $controller[1]);
        }

        if ($controller instanceof \Closure) {
            return new \ReflectionFunction($controller);
        }

        if (is_object($controller) && method_exists($controller, '__invoke')) {
            return new \ReflectionMethod($controller, '__invoke');
        }

        if (is_string($controller)) {
            return new \ReflectionFunction($controller);
        }

        throw new \InvalidArgumentException('Callable non supporté pour la résolution des arguments.');
    }
}
