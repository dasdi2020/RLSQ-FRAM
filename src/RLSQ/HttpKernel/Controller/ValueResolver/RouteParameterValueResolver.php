<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller\ValueResolver;

use RLSQ\HttpFoundation\Request;

/**
 * Résout les paramètres de route (scalaires) depuis $request->attributes.
 * Cast automatique : int, float, bool.
 */
class RouteParameterValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, \ReflectionParameter $parameter): array
    {
        $name = $parameter->getName();

        if (!$request->attributes->has($name)) {
            return [];
        }

        $value = $request->attributes->get($name);
        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
            $value = match ($type->getName()) {
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => (bool) $value,
                'array' => (array) $value,
                default => $value,
            };
        }

        return [$value];
    }
}
