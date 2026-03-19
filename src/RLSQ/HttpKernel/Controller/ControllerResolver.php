<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller;

use RLSQ\Controller\ContainerAwareInterface;
use RLSQ\DependencyInjection\ContainerInterface;
use RLSQ\HttpFoundation\Request;

class ControllerResolver implements ControllerResolverInterface
{
    public function __construct(
        private readonly ?ContainerInterface $container = null,
    ) {}

    public function getController(Request $request): callable|false
    {
        $controller = $request->attributes->get('_controller');

        if ($controller === null) {
            return false;
        }

        // Déjà un callable (closure, invocable, etc.)
        if (is_callable($controller)) {
            return $controller;
        }

        // Format string "App\Controller\BlogController::show"
        if (is_string($controller) && str_contains($controller, '::')) {
            [$class, $method] = explode('::', $controller, 2);

            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('La classe contrôleur "%s" n\'existe pas.', $class));
            }

            $instance = $this->instantiateController($class);

            if (!method_exists($instance, $method)) {
                throw new \InvalidArgumentException(sprintf('La méthode "%s::%s" n\'existe pas.', $class, $method));
            }

            return [$instance, $method];
        }

        // Format string "App\Controller\InvocableController" (__invoke)
        if (is_string($controller) && class_exists($controller)) {
            $instance = $this->instantiateController($controller);

            if (!is_callable($instance)) {
                throw new \InvalidArgumentException(sprintf('Le contrôleur "%s" n\'est pas invocable (__invoke manquant).', $controller));
            }

            return $instance;
        }

        throw new \InvalidArgumentException(sprintf('Impossible de résoudre le contrôleur "%s".', is_string($controller) ? $controller : get_debug_type($controller)));
    }

    /**
     * Instancie un contrôleur et injecte le container si ContainerAware.
     */
    private function instantiateController(string $class): object
    {
        // Si le service existe dans le container, l'utiliser
        if ($this->container !== null && $this->container->has($class)) {
            return $this->container->get($class);
        }

        $instance = new $class();

        if ($instance instanceof ContainerAwareInterface && $this->container !== null) {
            $instance->setContainer($this->container);
        }

        return $instance;
    }
}
