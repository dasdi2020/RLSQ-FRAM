<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\Response;

class ResponseTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $response = new Response();

        $this->assertSame('', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testConstructor(): void
    {
        $response = new Response('Hello', 201, ['X-Custom' => 'test']);

        $this->assertSame('Hello', $response->getContent());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('test', $response->headers->get('X-Custom'));
    }

    public function testSetContent(): void
    {
        $response = new Response();
        $result = $response->setContent('new content');

        $this->assertSame('new content', $response->getContent());
        $this->assertSame($response, $result); // fluent
    }

    public function testSetStatusCode(): void
    {
        $response = new Response();
        $result = $response->setStatusCode(404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($response, $result); // fluent
    }

    public function testStatusConstants(): void
    {
        $this->assertSame(200, Response::HTTP_OK);
        $this->assertSame(404, Response::HTTP_NOT_FOUND);
        $this->assertSame(500, Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertSame(302, Response::HTTP_FOUND);
    }
}
