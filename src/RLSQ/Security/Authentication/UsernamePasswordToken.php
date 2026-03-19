<?php

declare(strict_types=1);

namespace RLSQ\Security\Authentication;

use RLSQ\Security\User\UserInterface;

class UsernamePasswordToken implements TokenInterface
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        private readonly UserInterface $user,
        private readonly array $roles,
    ) {}

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isAuthenticated(): bool
    {
        return true;
    }

    public function getUserIdentifier(): string
    {
        return $this->user->getUserIdentifier();
    }
}
