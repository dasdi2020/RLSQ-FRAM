<?php

declare(strict_types=1);

namespace RLSQ\Security\User;

class InMemoryUser implements UserInterface
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        private readonly string $identifier,
        private readonly ?string $password = null,
        private readonly array $roles = ['ROLE_USER'],
    ) {}

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
