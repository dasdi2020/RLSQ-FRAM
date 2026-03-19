<?php

declare(strict_types=1);

namespace RLSQ\Security\Authentication;

use RLSQ\Security\User\UserInterface;

interface TokenInterface
{
    public function getUser(): ?UserInterface;

    /**
     * @return string[]
     */
    public function getRoles(): array;

    public function isAuthenticated(): bool;
}
