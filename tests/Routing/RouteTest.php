<?php

declare(strict_types=1);

namespace Tests\Routing;

use PHPUnit\Framework\TestCase;
use RLSQ\Routing\Route;

class RouteTest extends TestCase
{
    public function testBasicRoute(): void
    {
        $route = new Route('/home', ['_controller' => 'HomeController::index']);

        $this->assertSame('/home', $route->getPath());
        $this->assertSame('HomeController::index', $route->getController());
        $this->assertSame([], $route->getMethods());
    }

    public function testPathAlwaysStartsWithSlash(): void
    {
        $route = new Route('no-slash');

        $this->assertSame('/no-slash', $route->getPath());
    }

    public function testMethods(): void
    {
        $route = new Route('/submit', [], ['post', 'put']);

        $this->assertSame(['POST', 'PUT'], $route->getMethods());
        $this->assertTrue($route->allowsMethod('POST'));
        $this->assertTrue($route->allowsMethod('put'));
        $this->assertFalse($route->allowsMethod('GET'));
    }

    public function testEmptyMethodsAllowsAll(): void
    {
        $route = new Route('/any');

        $this->assertTrue($route->allowsMethod('GET'));
        $this->assertTrue($route->allowsMethod('DELETE'));
    }

    public function testCompileSimplePath(): void
    {
        $route = new Route('/about');

        $this->assertSame('#^/about$#', $route->compile());
        $this->assertSame([], $route->getParameterNames());
    }

    public function testCompileWithParameters(): void
    {
        $route = new Route('/article/{id}');

        $regex = $route->compile();
        $this->assertSame('#^/article/(?P<id>[^/]+)$#', $regex);
        $this->assertSame(['id'], $route->getParameterNames());

        $this->assertSame(1, preg_match($regex, '/article/42', $m));
        $this->assertSame('42', $m['id']);
    }

    public function testCompileWithMultipleParameters(): void
    {
        $route = new Route('/blog/{year}/{slug}');

        $regex = $route->compile();
        $this->assertSame(['year', 'slug'], $route->getParameterNames());

        preg_match($regex, '/blog/2026/mon-article', $m);
        $this->assertSame('2026', $m['year']);
        $this->assertSame('mon-article', $m['slug']);
    }

    public function testCompileWithRequirements(): void
    {
        $route = new Route('/article/{id}', [], [], ['id' => '\d+']);

        $regex = $route->compile();
        $this->assertSame('#^/article/(?P<id>\d+)$#', $regex);

        $this->assertSame(1, preg_match($regex, '/article/42'));
        $this->assertSame(0, preg_match($regex, '/article/abc'));
    }

    public function testDefaults(): void
    {
        $route = new Route('/page/{page}', ['page' => '1', '_controller' => 'PageController::list']);

        $this->assertSame('1', $route->getDefault('page'));
        $this->assertSame('PageController::list', $route->getDefault('_controller'));
        $this->assertNull($route->getDefault('nonexistent'));
    }

    public function testSetters(): void
    {
        $route = new Route('/old');
        $route->setPath('/new');
        $route->setMethods(['PATCH']);
        $route->setDefault('_controller', 'Test::action');
        $route->setRequirement('id', '\d+');

        $this->assertSame('/new', $route->getPath());
        $this->assertSame(['PATCH'], $route->getMethods());
        $this->assertSame('Test::action', $route->getDefault('_controller'));
        $this->assertSame('\d+', $route->getRequirement('id'));
    }

    public function testSetPathResetsCompiledRegex(): void
    {
        $route = new Route('/first/{id}');
        $regex1 = $route->compile();

        $route->setPath('/second/{id}');
        $regex2 = $route->compile();

        $this->assertNotSame($regex1, $regex2);
        $this->assertStringContainsString('second', $regex2);
    }
}
