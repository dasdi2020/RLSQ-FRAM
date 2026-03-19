<?php

declare(strict_types=1);

namespace Tests\HttpKernel\Controller;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;

class ArgumentResolverTest extends TestCase
{
    private ArgumentResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ArgumentResolver();
    }

    public function testInjectsRequest(): void
    {
        $controller = function (Request $request): Response {
            return new Response('ok');
        };

        $request = Request::create('/');
        $args = $this->resolver->getArguments($request, $controller);

        $this->assertCount(1, $args);
        $this->assertSame($request, $args[0]);
    }

    public function testInjectsRouteParameters(): void
    {
        $controller = function (string $slug, int $id): Response {
            return new Response('ok');
        };

        $request = Request::create('/');
        $request->attributes->set('slug', 'hello-world');
        $request->attributes->set('id', '42');

        $args = $this->resolver->getArguments($request, $controller);

        $this->assertSame('hello-world', $args[0]);
        $this->assertSame(42, $args[1]);  // Cast en int
    }

    public function testMixedRequestAndParams(): void
    {
        $controller = function (Request $request, int $id): Response {
            return new Response('ok');
        };

        $request = Request::create('/');
        $request->attributes->set('id', '7');

        $args = $this->resolver->getArguments($request, $controller);

        $this->assertSame($request, $args[0]);
        $this->assertSame(7, $args[1]);
    }

    public function testUsesDefaultValue(): void
    {
        $controller = function (int $page = 1): Response {
            return new Response('ok');
        };

        $request = Request::create('/');
        $args = $this->resolver->getArguments($request, $controller);

        $this->assertSame(1, $args[0]);
    }

    public function testNullableParam(): void
    {
        $controller = function (?string $filter): Response {
            return new Response('ok');
        };

        $request = Request::create('/');
        $args = $this->resolver->getArguments($request, $controller);

        $this->assertNull($args[0]);
    }

    public function testThrowsOnUnresolvable(): void
    {
        $controller = function (string $required): Response {
            return new Response('ok');
        };

        $request = Request::create('/');

        $this->expectException(\RuntimeException::class);
        $this->resolver->getArguments($request, $controller);
    }

    public function testResolvesMethodCallable(): void
    {
        $obj = new class {
            public function show(int $id, Request $request): Response
            {
                return new Response('ok');
            }
        };

        $request = Request::create('/');
        $request->attributes->set('id', '99');

        $args = $this->resolver->getArguments($request, [$obj, 'show']);

        $this->assertSame(99, $args[0]);
        $this->assertSame($request, $args[1]);
    }

    public function testCastsFloat(): void
    {
        $controller = function (float $price): Response {
            return new Response('ok');
        };

        $request = Request::create('/');
        $request->attributes->set('price', '19.99');

        $args = $this->resolver->getArguments($request, $controller);

        $this->assertSame(19.99, $args[0]);
    }
}
