<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\RedirectResponse;

class RedirectResponseTest extends TestCase
{
    public function testRedirect(): void
    {
        $response = new RedirectResponse('/login');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('location'));
        $this->assertSame('/login', $response->getTargetUrl());
    }

    public function testRedirect301(): void
    {
        $response = new RedirectResponse('/new-url', 301);

        $this->assertSame(301, $response->getStatusCode());
    }

    public function testInvalidStatusThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RedirectResponse('/test', 200);
    }

    public function testBodyContainsLink(): void
    {
        $response = new RedirectResponse('/target');

        $this->assertStringContainsString('/target', $response->getContent());
    }
}
