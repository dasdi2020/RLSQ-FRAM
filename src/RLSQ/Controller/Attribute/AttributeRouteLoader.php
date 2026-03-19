<?php

declare(strict_types=1);

namespace RLSQ\Controller\Attribute;

use RLSQ\Routing\Route as RouteDef;
use RLSQ\Routing\RouteCollection;

/**
 * Charge les routes définies par des attributs #[Route] sur les contrôleurs.
 */
class AttributeRouteLoader
{
    /**
     * Scanne une classe contrôleur et extrait ses routes.
     */
    public function load(string $class): RouteCollection
    {
        $collection = new RouteCollection();
        $ref = new \ReflectionClass($class);

        // Préfixe depuis l'attribut sur la classe
        $classPrefix = '';
        $classAttributes = $ref->getAttributes(Route::class);
        if (!empty($classAttributes)) {
            $classRoute = $classAttributes[0]->newInstance();
            $classPrefix = $classRoute->path;
        }

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();

                $path = rtrim($classPrefix, '/') . '/' . ltrim($route->path, '/');
                if ($path !== '/') {
                    $path = rtrim($path, '/');
                }

                $name = $route->name ?? $this->generateRouteName($class, $method->getName());

                $defaults = array_merge($route->defaults, [
                    '_controller' => $class . '::' . $method->getName(),
                ]);

                $collection->add($name, new RouteDef(
                    $path,
                    $defaults,
                    $route->methods,
                    $route->requirements,
                ));
            }
        }

        return $collection;
    }

    /**
     * Scanne plusieurs classes et retourne une collection fusionnée.
     *
     * @param string[] $classes
     */
    public function loadAll(array $classes): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($classes as $class) {
            $collection->addCollection($this->load($class));
        }

        return $collection;
    }

    /**
     * Génère un nom de route automatique : app_controller_method
     */
    private function generateRouteName(string $class, string $method): string
    {
        // App\Controller\ArticleController::show → app_article_show
        $short = str_replace('\\', '_', $class);
        $short = strtolower($short);
        $short = str_replace('_controller', '', $short);

        return $short . '_' . strtolower($method);
    }
}
