<?php

declare(strict_types=1);

namespace RLSQ\Security\Hasher;

class NativePasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private readonly string $algorithm = PASSWORD_ARGON2ID,
        private readonly array $options = [],
    ) {}

    public function hash(string $plainPassword): string
    {
        $hash = password_hash($plainPassword, $this->algorithm, $this->options);

        if ($hash === false) {
            throw new \RuntimeException('Impossible de hasher le mot de passe.');
        }

        return $hash;
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return password_needs_rehash($hashedPassword, $this->algorithm, $this->options);
    }
}
