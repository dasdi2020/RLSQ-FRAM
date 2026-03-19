<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller\ValueResolver;

use RLSQ\HttpFoundation\Request;

/**
 * Injecte la Request si le paramètre type-hint Request.
 */
class RequestValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, \ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $typeName = $type->getName();

        if ($typeName === Request::class || is_subclass_of($typeName, Request::class)) {
            return [$request];
        }

        return [];
    }
}
