<?php

declare(strict_types=1);

namespace Tests\Routing;

use PHPUnit\Framework\TestCase;
use RLSQ\Routing\Exception\MethodNotAllowedException;
use RLSQ\Routing\Exception\RouteNotFoundException;
use RLSQ\Routing\Matcher\UrlMatcher;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

class UrlMatcherTest extends TestCase
{
    private function createMatcher(array $routes): UrlMatcher
    {
        $collection = new RouteCollection();
        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        return new UrlMatcher($collection);
    }

    public function testMatchSimpleRoute(): void
    {
        $matcher = $this->createMatcher([
            'home' => new Route('/', ['_controller' => 'HomeController::index']),
        ]);

        $result = $matcher->match('/');

        $this->assertSame('home', $result['_route']);
        $this->assertSame('HomeController::index', $result['_controller']);
    }

    public function testMatchWithParameter(): void
    {
        $matcher = $this->createMatcher([
            'article_show' => new Route('/article/{id}', ['_controller' => 'ArticleController::show']),
        ]);

        $result = $matcher->match('/article/42');

        $this->assertSame('article_show', $result['_route']);
        $this->assertSame('42', $result['id']);
    }

    public function testMatchWithMultipleParameters(): void
    {
        $matcher = $this->createMatcher([
            'blog_post' => new Route('/blog/{year}/{slug}', ['_controller' => 'BlogController::post']),
        ]);

        $result = $matcher->match('/blog/2026/hello-world');

        $this->assertSame('2026', $result['year']);
        $this->assertSame('hello-world', $result['slug']);
    }

    public function testMatchWithRequirement(): void
    {
        $matcher = $this->createMatcher([
            'user' => new Route('/user/{id}', ['_controller' => 'UserController::show'], [], ['id' => '\d+']),
        ]);

        $result = $matcher->match('/user/123');
        $this->assertSame('123', $result['id']);
    }

    public function testRequirementRejectsInvalidParam(): void
    {
        $matcher = $this->createMatcher([
            'user' => new Route('/user/{id}', ['_controller' => 'UserController::show'], [], ['id' => '\d+']),
        ]);

        $this->expectException(RouteNotFoundException::class);
        $matcher->match('/user/abc');
    }

    public function testMatchRespectsHttpMethod(): void
    {
        $matcher = $this->createMatcher([
            'create' => new Route('/items', ['_controller' => 'ItemController::create'], ['POST']),
            'list'   => new Route('/items', ['_controller' => 'ItemController::list'], ['GET']),
        ]);

        $get = $matcher->match('/items', 'GET');
        $this->assertSame('list', $get['_route']);

        $post = $matcher->match('/items', 'POST');
        $this->assertSame('create', $post['_route']);
    }

    public function testMethodNotAllowed(): void
    {
        $matcher = $this->createMatcher([
            'only_post' => new Route('/submit', ['_controller' => 'FormController::submit'], ['POST']),
        ]);

        try {
            $matcher->match('/submit', 'GET');
            $this->fail('MethodNotAllowedException attendue.');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(['POST'], $e->getAllowedMethods());
        }
    }

    public function testRouteNotFound(): void
    {
        $matcher = $this->createMatcher([
            'home' => new Route('/', ['_controller' => 'HomeController::index']),
        ]);

        $this->expectException(RouteNotFoundException::class);
        $matcher->match('/nonexistent');
    }

    public function testFirstMatchingRouteWins(): void
    {
        $matcher = $this->createMatcher([
            'catch_all' => new Route('/page/{slug}', ['_controller' => 'PageController::show']),
            'specific'  => new Route('/page/about', ['_controller' => 'PageController::about']),
        ]);

        $result = $matcher->match('/page/about');

        // La première route définie gagne
        $this->assertSame('catch_all', $result['_route']);
    }

    public function testMatchIncludesDefaults(): void
    {
        $matcher = $this->createMatcher([
            'list' => new Route('/items', [
                '_controller' => 'ItemController::list',
                'page' => 1,
                'limit' => 10,
            ]),
        ]);

        $result = $matcher->match('/items');

        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['limit']);
    }
}
