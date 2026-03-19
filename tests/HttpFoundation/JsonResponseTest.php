<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function testEncodesArray(): void
    {
        $response = new JsonResponse(['status' => 'ok']);

        $this->assertSame('{"status":"ok"}', $response->getContent());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testEncodesNested(): void
    {
        $response = new JsonResponse(['users' => [['name' => 'Alice'], ['name' => 'Bob']]]);

        $decoded = json_decode($response->getContent(), true);
        $this->assertCount(2, $decoded['users']);
    }

    public function testCustomStatusCode(): void
    {
        $response = new JsonResponse(['error' => 'Not found'], 404);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testSetData(): void
    {
        $response = new JsonResponse();
        $response->setData(['key' => 'value']);

        $this->assertSame('{"key":"value"}', $response->getContent());
    }

    public function testUnicodeNotEscaped(): void
    {
        $response = new JsonResponse(['message' => 'Réussi']);

        $this->assertStringContainsString('Réussi', $response->getContent());
    }
}
