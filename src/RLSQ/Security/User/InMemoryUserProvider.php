<?php

declare(strict_types=1);

namespace RLSQ\Security\User;

class InMemoryUserProvider implements UserProviderInterface
{
    /** @var array<string, InMemoryUser> */
    private array $users = [];

    /**
     * @param InMemoryUser[] $users
     */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $this->users[$user->getUserIdentifier()] = $user;
        }
    }

    public function addUser(InMemoryUser $user): void
    {
        $this->users[$user->getUserIdentifier()] = $user;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (!isset($this->users[$identifier])) {
            throw new UserNotFoundException($identifier);
        }

        return $this->users[$identifier];
    }

    public function supportsClass(string $class): bool
    {
        return $class === InMemoryUser::class || is_subclass_of($class, InMemoryUser::class);
    }
}
