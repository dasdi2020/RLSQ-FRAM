<?php

declare(strict_types=1);

namespace RLSQ\Security\TwoFactor;

use RLSQ\Database\Connection;

/**
 * Gère la génération et vérification des codes 2FA par email.
 */
class TwoFactorManager
{
    private const CODE_LENGTH = 6;
    private const CODE_TTL = 600; // 10 minutes

    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Initialise la table 2FA (à appeler lors du setup).
     */
    public function createTable(): void
    {
        $this->connection->exec('
            CREATE TABLE IF NOT EXISTS two_factor_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                email VARCHAR(255) NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    /**
     * Génère un code 2FA pour un utilisateur.
     */
    public function generateCode(int $userId, string $email): string
    {
        // Invalider les codes précédents
        $this->connection->execute(
            'UPDATE two_factor_codes SET used = 1 WHERE user_id = :uid AND used = 0',
            ['uid' => $userId],
        );

        $code = $this->createCode();
        $expiresAt = date('Y-m-d H:i:s', time() + self::CODE_TTL);

        $this->connection->execute(
            'INSERT INTO two_factor_codes (user_id, email, code, expires_at) VALUES (:uid, :email, :code, :exp)',
            ['uid' => $userId, 'email' => $email, 'code' => $code, 'exp' => $expiresAt],
        );

        return $code;
    }

    /**
     * Vérifie un code 2FA. Retourne true si valide.
     */
    public function verifyCode(int $userId, string $code): bool
    {
        $row = $this->connection->fetchOne(
            'SELECT id FROM two_factor_codes WHERE user_id = :uid AND code = :code AND used = 0 AND expires_at > :now LIMIT 1',
            ['uid' => $userId, 'code' => $code, 'now' => date('Y-m-d H:i:s')],
        );

        if ($row === false) {
            return false;
        }

        // Marquer comme utilisé
        $this->connection->execute(
            'UPDATE two_factor_codes SET used = 1 WHERE id = :id',
            ['id' => $row['id']],
        );

        return true;
    }

    /**
     * Nettoie les codes expirés.
     */
    public function cleanup(): void
    {
        $this->connection->execute(
            'DELETE FROM two_factor_codes WHERE expires_at < :now OR used = 1',
            ['now' => date('Y-m-d H:i:s', time() - 3600)],
        );
    }

    private function createCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
