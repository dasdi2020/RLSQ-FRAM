<?php

declare(strict_types=1);

namespace RLSQ\Security\Authentication;

use RLSQ\Security\User\UserInterface;

/**
 * Résultat d'une authentification réussie.
 */
class Passport
{
    public function __construct(
        private readonly UserInterface $user,
        private readonly array $attributes = [],
    ) {}

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
