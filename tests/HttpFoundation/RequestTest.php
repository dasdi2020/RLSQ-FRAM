<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\Request;

class RequestTest extends TestCase
{
    public function testCreateSetsMethodAndPath(): void
    {
        $request = Request::create('/articles/5', 'GET');

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/articles/5', $request->getPathInfo());
    }

    public function testCreatePost(): void
    {
        $request = Request::create('/login', 'POST', ['username' => 'admin']);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('admin', $request->request->get('username'));
    }

    public function testCreateWithQueryString(): void
    {
        $request = Request::create('/search?q=php&page=2');

        $this->assertSame('php', $request->query->get('q'));
        $this->assertSame('2', $request->query->get('page'));
        $this->assertSame('/search', $request->getPathInfo());
    }

    public function testGetMethodWithOverride(): void
    {
        $request = Request::create('/resource', 'POST', ['_method' => 'PUT']);

        $this->assertSame('PUT', $request->getMethod());
    }

    public function testIsMethod(): void
    {
        $request = Request::create('/test', 'DELETE');

        $this->assertTrue($request->isMethod('DELETE'));
        $this->assertTrue($request->isMethod('delete'));
        $this->assertFalse($request->isMethod('GET'));
    }

    public function testGetHost(): void
    {
        $request = Request::create('/test');

        $this->assertSame('localhost', $request->getHost());
    }

    public function testGetScheme(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], ['HTTPS' => 'on']);

        $this->assertSame('https', $request->getScheme());
    }

    public function testGetUri(): void
    {
        $request = Request::create('/articles?page=3', 'GET', [], [], [], [
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '443',
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
        ]);

        $this->assertSame('https://example.com/articles?page=3', $request->getUri());
    }

    public function testGetContent(): void
    {
        $request = Request::create('/api/data', 'POST', [], [], [], [], '{"key":"value"}');

        $this->assertSame('{"key":"value"}', $request->getContent());
    }

    public function testAttributes(): void
    {
        $request = Request::create('/test');
        $request->attributes->set('_controller', 'App\\Controller\\HomeController::index');
        $request->attributes->set('id', 42);

        $this->assertSame('App\\Controller\\HomeController::index', $request->attributes->get('_controller'));
        $this->assertSame(42, $request->attributes->get('id'));
    }

    public function testIsXmlHttpRequest(): void
    {
        $request = Request::create('/api', 'GET', [], [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $this->assertTrue($request->isXmlHttpRequest());
    }

    public function testNotXmlHttpRequest(): void
    {
        $request = Request::create('/page');

        $this->assertFalse($request->isXmlHttpRequest());
    }

    public function testSessionThrowsWhenNotSet(): void
    {
        $request = Request::create('/test');

        $this->assertFalse($request->hasSession());

        $this->expectException(\LogicException::class);
        $request->getSession();
    }

    public function testGetClientIp(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);

        $this->assertSame('192.168.1.1', $request->getClientIp());
    }

    public function testGetClientIpWithProxy(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ]);

        $this->assertSame('203.0.113.50', $request->getClientIp());
    }
}
