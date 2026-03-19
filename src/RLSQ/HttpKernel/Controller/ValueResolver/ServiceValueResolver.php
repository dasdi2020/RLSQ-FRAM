<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller\ValueResolver;

use RLSQ\DependencyInjection\ContainerInterface;
use RLSQ\HttpFoundation\Request;

/**
 * Injecte un service du Container par type-hint.
 *
 * Usage :
 *   public function index(Mailer $mailer, LoggerInterface $logger): Response { ... }
 */
class ServiceValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function resolve(Request $request, \ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $typeName = $type->getName();

        // Ne pas résoudre les classes du framework que d'autres resolvers gèrent
        if ($typeName === Request::class || is_subclass_of($typeName, Request::class)) {
            return [];
        }

        if ($this->container->has($typeName)) {
            return [$this->container->get($typeName)];
        }

        return [];
    }
}
