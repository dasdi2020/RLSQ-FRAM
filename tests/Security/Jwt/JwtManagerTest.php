<?php

declare(strict_types=1);

namespace Tests\Security\Jwt;

use PHPUnit\Framework\TestCase;
use RLSQ\Security\Jwt\JwtManager;

class JwtManagerTest extends TestCase
{
    private JwtManager $jwt;

    protected function setUp(): void
    {
        $this->jwt = new JwtManager('test_secret_key_123', ttl: 60, refreshTtl: 3600);
    }

    public function testCreateAndDecodeToken(): void
    {
        $token = $this->jwt->createToken(['user_id' => 42, 'email' => 'test@test.com']);

        $this->assertIsString($token);
        $this->assertCount(3, explode('.', $token)); // header.payload.signature

        $payload = $this->jwt->decode($token);

        $this->assertNotNull($payload);
        $this->assertSame(42, $payload['user_id']);
        $this->assertSame('test@test.com', $payload['email']);
        $this->assertSame('access', $payload['type']);
    }

    public function testAccessTokenValidation(): void
    {
        $token = $this->jwt->createToken(['user_id' => 1]);

        $payload = $this->jwt->validateAccessToken($token);
        $this->assertNotNull($payload);
        $this->assertSame(1, $payload['user_id']);
    }

    public function testRefreshToken(): void
    {
        $token = $this->jwt->createRefreshToken(['user_id' => 1]);

        $this->assertNull($this->jwt->validateAccessToken($token)); // Pas un access token
        $payload = $this->jwt->validateRefreshToken($token);
        $this->assertNotNull($payload);
        $this->assertSame(1, $payload['user_id']);
        $this->assertArrayHasKey('jti', $payload);
    }

    public function testExpiredToken(): void
    {
        $jwt = new JwtManager('secret', ttl: -1); // TTL négatif = déjà expiré
        $token = $jwt->createToken(['user_id' => 1]);

        $this->assertNull($jwt->decode($token));
    }

    public function testTamperedTokenFails(): void
    {
        $token = $this->jwt->createToken(['user_id' => 1]);

        // Modifier le payload
        $parts = explode('.', $token);
        $parts[1] = $parts[1] . 'tampered';
        $tampered = implode('.', $parts);

        $this->assertNull($this->jwt->decode($tampered));
    }

    public function testWrongSecretFails(): void
    {
        $token = $this->jwt->createToken(['user_id' => 1]);

        $otherJwt = new JwtManager('different_secret');
        $this->assertNull($otherJwt->decode($token));
    }

    public function testInvalidFormatReturnsNull(): void
    {
        $this->assertNull($this->jwt->decode('not.a.valid.jwt'));
        $this->assertNull($this->jwt->decode('garbage'));
        $this->assertNull($this->jwt->decode(''));
    }

    public function testGetTtl(): void
    {
        $this->assertSame(60, $this->jwt->getTtl());
    }
}
