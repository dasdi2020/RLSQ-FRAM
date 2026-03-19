<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller\ValueResolver;

use RLSQ\HttpFoundation\Request;

/**
 * Fournit la valeur par défaut ou null pour les paramètres non résolus.
 * Doit toujours être le dernier resolver dans la chaîne.
 */
class DefaultValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, \ReflectionParameter $parameter): array
    {
        if ($parameter->isDefaultValueAvailable()) {
            return [$parameter->getDefaultValue()];
        }

        if ($parameter->allowsNull()) {
            return [null];
        }

        return [];
    }
}
