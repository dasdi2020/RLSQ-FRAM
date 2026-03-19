<?php

declare(strict_types=1);

namespace Tests\Routing;

use PHPUnit\Framework\TestCase;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

class RouteCollectionTest extends TestCase
{
    public function testAddAndGet(): void
    {
        $collection = new RouteCollection();
        $route = new Route('/home');

        $collection->add('home', $route);

        $this->assertSame($route, $collection->get('home'));
        $this->assertTrue($collection->has('home'));
        $this->assertNull($collection->get('nonexistent'));
    }

    public function testRemove(): void
    {
        $collection = new RouteCollection();
        $collection->add('home', new Route('/home'));

        $collection->remove('home');

        $this->assertFalse($collection->has('home'));
    }

    public function testAll(): void
    {
        $collection = new RouteCollection();
        $collection->add('a', new Route('/a'));
        $collection->add('b', new Route('/b'));

        $all = $collection->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('a', $all);
        $this->assertArrayHasKey('b', $all);
    }

    public function testCount(): void
    {
        $collection = new RouteCollection();

        $this->assertSame(0, $collection->count());

        $collection->add('r1', new Route('/r1'));
        $collection->add('r2', new Route('/r2'));

        $this->assertSame(2, $collection->count());
    }

    public function testAddCollection(): void
    {
        $main = new RouteCollection();
        $main->add('home', new Route('/home'));

        $api = new RouteCollection();
        $api->add('api_users', new Route('/api/users'));

        $main->addCollection($api);

        $this->assertSame(2, $main->count());
        $this->assertTrue($main->has('api_users'));
    }

    public function testAddPrefix(): void
    {
        $collection = new RouteCollection();
        $collection->add('users', new Route('/users'));
        $collection->add('posts', new Route('/posts'));

        $collection->addPrefix('/api/v1');

        $this->assertSame('/api/v1/users', $collection->get('users')->getPath());
        $this->assertSame('/api/v1/posts', $collection->get('posts')->getPath());
    }
}
