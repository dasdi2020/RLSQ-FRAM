<?php

declare(strict_types=1);

namespace RLSQ\Security\User;

interface UserInterface
{
    public function getUserIdentifier(): string;

    /**
     * @return string[]
     */
    public function getRoles(): array;

    public function getPassword(): ?string;
}
