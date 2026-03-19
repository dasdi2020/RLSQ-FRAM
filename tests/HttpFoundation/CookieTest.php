<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\Cookie;

class CookieTest extends TestCase
{
    public function testDefaults(): void
    {
        $cookie = new Cookie('session_id', 'abc123');

        $this->assertSame('session_id', $cookie->getName());
        $this->assertSame('abc123', $cookie->getValue());
        $this->assertSame(0, $cookie->getExpire());
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame('', $cookie->getDomain());
        $this->assertFalse($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('Lax', $cookie->getSameSite());
    }

    public function testCustomValues(): void
    {
        $cookie = new Cookie(
            name: 'token',
            value: 'xyz',
            expire: 1700000000,
            path: '/app',
            domain: '.example.com',
            secure: true,
            httpOnly: false,
            sameSite: 'Strict',
        );

        $this->assertSame('.example.com', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertSame('Strict', $cookie->getSameSite());
    }

    public function testToString(): void
    {
        $cookie = new Cookie('test', 'value');
        $str = (string) $cookie;

        $this->assertStringContainsString('test=value', $str);
        $this->assertStringContainsString('path=/', $str);
        $this->assertStringContainsString('httponly', $str);
        $this->assertStringContainsString('samesite=Lax', $str);
    }
}
