<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller;

use RLSQ\HttpFoundation\Request;

class ArgumentResolver implements ArgumentResolverInterface
{
    public function getArguments(Request $request, callable $controller): array
    {
        $reflection = $this->getReflection($controller);
        $arguments = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Si le paramètre type-hint Request, on injecte la Request
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($typeName === Request::class || is_subclass_of($typeName, Request::class)) {
                    $arguments[] = $request;
                    continue;
                }
            }

            // Chercher dans les attributs de la Request (paramètres de route)
            if ($request->attributes->has($name)) {
                $value = $request->attributes->get($name);

                // Cast automatique si le type est int ou float
                if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                    $value = match ($type->getName()) {
                        'int' => (int) $value,
                        'float' => (float) $value,
                        'bool' => (bool) $value,
                        default => $value,
                    };
                }

                $arguments[] = $value;
                continue;
            }

            // Valeur par défaut
            if ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
                continue;
            }

            // Nullable
            if ($param->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Impossible de résoudre l\'argument "$%s" du contrôleur.',
                $name,
            ));
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
