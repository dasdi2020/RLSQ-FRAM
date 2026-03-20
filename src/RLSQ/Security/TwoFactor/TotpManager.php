<?php

declare(strict_types=1);

namespace RLSQ\Security\TwoFactor;

/**
 * TOTP (Time-based One-Time Password) — compatible Google Authenticator,
 * Microsoft Authenticator, Authy, etc.
 *
 * Implémentation RFC 6238 pure PHP sans dépendance.
 */
class TotpManager
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALGORITHM = 'sha1';

    /**
     * Génère un secret TOTP aléatoire (base32 encodé).
     */
    public function generateSecret(int $length = 20): string
    {
        $random = random_bytes($length);
        return $this->base32Encode($random);
    }

    /**
     * Génère l'URI otpauth:// pour le QR code.
     */
    public function getProvisioningUri(string $secret, string $email, string $issuer = 'RLSQ-FRAM'): string
    {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);

        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($email),
            $params,
        );
    }

    /**
     * Génère l'URL vers l'API Google Charts pour afficher le QR code.
     */
    public function getQrCodeUrl(string $provisioningUri, int $size = 250): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => "{$size}x{$size}",
            'data' => $provisioningUri,
            'ecc' => 'M',
        ]);
    }

    /**
     * Vérifie un code TOTP. Accepte une fenêtre de ±1 période (30s de tolérance).
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $secret = $this->base32Decode($secret);
        $currentTime = (int) floor(time() / self::PERIOD);

        for ($i = -$window; $i <= $window; $i++) {
            $expected = $this->generateCode($secret, $currentTime + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Génère le code TOTP courant (pour debug/test).
     */
    public function getCurrentCode(string $secret): string
    {
        $decodedSecret = $this->base32Decode($secret);
        $currentTime = (int) floor(time() / self::PERIOD);

        return $this->generateCode($decodedSecret, $currentTime);
    }

    private function generateCode(string $secret, int $counter): string
    {
        // Pack counter as 8 bytes big-endian
        $time = pack('N*', 0, $counter);

        // HMAC-SHA1
        $hash = hash_hmac(self::ALGORITHM, $time, $secret, true);

        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );

        $otp = $binary % (10 ** self::DIGITS);

        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        $chunks = str_split($binary, 5);

        foreach ($chunks as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper(rtrim($data, '='));
        $binary = '';

        foreach (str_split($data) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        $chunks = str_split($binary, 8);

        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }
}
