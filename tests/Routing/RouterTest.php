<?php

declare(strict_types=1);

namespace Tests\Routing;

use PHPUnit\Framework\TestCase;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;
use RLSQ\Routing\Router;

class RouterTest extends TestCase
{
    private function createRouter(): Router
    {
        $routes = new RouteCollection();
        $routes->add('home', new Route('/', ['_controller' => 'HomeController::index']));
        $routes->add('article', new Route('/article/{id}', ['_controller' => 'ArticleController::show'], ['GET'], ['id' => '\d+']));

        return new Router($routes);
    }

    public function testMatchDelegatesToMatcher(): void
    {
        $router = $this->createRouter();
        $result = $router->match('/article/7', 'GET');

        $this->assertSame('article', $result['_route']);
        $this->assertSame('7', $result['id']);
    }

    public function testGenerateDelegatesToGenerator(): void
    {
        $router = $this->createRouter();

        $this->assertSame('/article/99', $router->generate('article', ['id' => 99]));
    }

    public function testGetRouteCollection(): void
    {
        $router = $this->createRouter();

        $this->assertSame(2, $router->getRouteCollection()->count());
    }
}
