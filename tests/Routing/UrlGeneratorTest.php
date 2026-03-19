<?php

declare(strict_types=1);

namespace Tests\Routing;

use PHPUnit\Framework\TestCase;
use RLSQ\Routing\Exception\RouteNotFoundException;
use RLSQ\Routing\Generator\UrlGenerator;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

class UrlGeneratorTest extends TestCase
{
    private function createGenerator(array $routes): UrlGenerator
    {
        $collection = new RouteCollection();
        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        return new UrlGenerator($collection);
    }

    public function testGenerateSimplePath(): void
    {
        $generator = $this->createGenerator([
            'home' => new Route('/'),
        ]);

        $this->assertSame('/', $generator->generate('home'));
    }

    public function testGenerateWithParameter(): void
    {
        $generator = $this->createGenerator([
            'article' => new Route('/article/{id}'),
        ]);

        $this->assertSame('/article/42', $generator->generate('article', ['id' => 42]));
    }

    public function testGenerateWithMultipleParameters(): void
    {
        $generator = $this->createGenerator([
            'blog' => new Route('/blog/{year}/{slug}'),
        ]);

        $url = $generator->generate('blog', ['year' => 2026, 'slug' => 'hello']);

        $this->assertSame('/blog/2026/hello', $url);
    }

    public function testGenerateUsesDefaultValues(): void
    {
        $generator = $this->createGenerator([
            'list' => new Route('/items/{page}', ['page' => '1']),
        ]);

        $this->assertSame('/items/1', $generator->generate('list'));
        $this->assertSame('/items/3', $generator->generate('list', ['page' => 3]));
    }

    public function testExtraParametersAsQueryString(): void
    {
        $generator = $this->createGenerator([
            'search' => new Route('/search'),
        ]);

        $url = $generator->generate('search', ['q' => 'php', 'page' => 2]);

        $this->assertSame('/search?q=php&page=2', $url);
    }

    public function testMixedPathAndQueryParameters(): void
    {
        $generator = $this->createGenerator([
            'category' => new Route('/category/{slug}'),
        ]);

        $url = $generator->generate('category', ['slug' => 'php', 'sort' => 'date']);

        $this->assertSame('/category/php?sort=date', $url);
    }

    public function testInternalParamsExcludedFromQuery(): void
    {
        $generator = $this->createGenerator([
            'home' => new Route('/', ['_controller' => 'HomeController::index']),
        ]);

        $url = $generator->generate('home', ['_controller' => 'ignored']);

        $this->assertSame('/', $url);
    }

    public function testRouteNotFound(): void
    {
        $generator = $this->createGenerator([]);

        $this->expectException(RouteNotFoundException::class);
        $generator->generate('nonexistent');
    }

    public function testMissingRequiredParameter(): void
    {
        $generator = $this->createGenerator([
            'article' => new Route('/article/{id}'),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $generator->generate('article');
    }
}
