<?php

declare(strict_types=1);

namespace RLSQ\Security\User;

interface UserProviderInterface
{
    /**
     * @throws UserNotFoundException
     */
    public function loadUserByIdentifier(string $identifier): UserInterface;

    public function supportsClass(string $class): bool;
}
