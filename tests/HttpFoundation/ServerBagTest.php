<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\ServerBag;

class ServerBagTest extends TestCase
{
    public function testGetHeaders(): void
    {
        $bag = new ServerBag([
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'text/html',
            'CONTENT_TYPE' => 'application/json',
            'SERVER_NAME' => 'example.com', // pas un header
        ]);

        $headers = $bag->getHeaders();

        $this->assertSame('example.com', $headers['host']);
        $this->assertSame('text/html', $headers['accept']);
        $this->assertSame('application/json', $headers['content-type']);
        $this->assertArrayNotHasKey('server-name', $headers);
    }

    public function testInheritsParameterBag(): void
    {
        $bag = new ServerBag(['REQUEST_METHOD' => 'POST']);

        $this->assertSame('POST', $bag->get('REQUEST_METHOD'));
        $this->assertTrue($bag->has('REQUEST_METHOD'));
    }
}
