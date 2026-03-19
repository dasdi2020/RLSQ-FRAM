<?php

declare(strict_types=1);

namespace Tests\HttpKernel\Controller;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ControllerResolver;

class ControllerResolverTest extends TestCase
{
    private ControllerResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ControllerResolver();
    }

    public function testReturnsFalseWhenNoController(): void
    {
        $request = Request::create('/');

        $this->assertFalse($this->resolver->getController($request));
    }

    public function testResolvesClosure(): void
    {
        $closure = function () { return new Response('ok'); };

        $request = Request::create('/');
        $request->attributes->set('_controller', $closure);

        $controller = $this->resolver->getController($request);

        $this->assertSame($closure, $controller);
    }

    public function testResolvesClassMethod(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', DummyController::class . '::index');

        $controller = $this->resolver->getController($request);

        $this->assertIsArray($controller);
        $this->assertInstanceOf(DummyController::class, $controller[0]);
        $this->assertSame('index', $controller[1]);
    }

    public function testResolvesInvocable(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', InvocableController::class);

        $controller = $this->resolver->getController($request);

        $this->assertInstanceOf(InvocableController::class, $controller);
        $this->assertIsCallable($controller);
    }

    public function testThrowsOnNonExistentClass(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', 'NonExistent\\FakeClass::method');

        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->getController($request);
    }

    public function testThrowsOnNonExistentMethod(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', DummyController::class . '::nonExistent');

        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->getController($request);
    }

    public function testThrowsOnNonInvocableClass(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', NonInvocableController::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->getController($request);
    }
}

class DummyController
{
    public function index(): Response
    {
        return new Response('index');
    }
}

class InvocableController
{
    public function __invoke(): Response
    {
        return new Response('invoked');
    }
}

class NonInvocableController
{
    // Pas de __invoke
}
