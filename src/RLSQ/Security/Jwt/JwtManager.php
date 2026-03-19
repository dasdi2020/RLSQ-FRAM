<?php

declare(strict_types=1);

namespace RLSQ\Security\Jwt;

/**
 * Création et validation de tokens JWT (HMAC-SHA256).
 */
class JwtManager
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl = 900,
        private readonly int $refreshTtl = 604800,
    ) {}

    /**
     * Crée un access token.
     */
    public function createToken(array $payload): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $this->ttl;
        $payload['type'] = 'access';

        return $this->encode($payload);
    }

    /**
     * Crée un refresh token.
     */
    public function createRefreshToken(array $payload): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $this->refreshTtl;
        $payload['type'] = 'refresh';
        $payload['jti'] = bin2hex(random_bytes(16));

        return $this->encode($payload);
    }

    /**
     * Valide et décode un token. Retourne null si invalide ou expiré.
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $expectedSig = $this->sign($headerB64 . '.' . $payloadB64);

        if (!hash_equals($expectedSig, $signatureB64)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);

        if ($payload === null) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Vérifie qu'un token est un access token valide.
     */
    public function validateAccessToken(string $token): ?array
    {
        $payload = $this->decode($token);

        if ($payload === null || ($payload['type'] ?? '') !== 'access') {
            return null;
        }

        return $payload;
    }

    /**
     * Vérifie qu'un token est un refresh token valide.
     */
    public function validateRefreshToken(string $token): ?array
    {
        $payload = $this->decode($token);

        if ($payload === null || ($payload['type'] ?? '') !== 'refresh') {
            return null;
        }

        return $payload;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign($header . '.' . $body);

        return $header . '.' . $body . '.' . $signature;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
