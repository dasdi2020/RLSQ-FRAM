<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use RLSQ\Security\User\InMemoryUser;
use RLSQ\Security\User\InMemoryUserProvider;
use RLSQ\Security\User\UserNotFoundException;

class UserProviderTest extends TestCase
{
    public function testLoadUser(): void
    {
        $provider = new InMemoryUserProvider([
            new InMemoryUser('alice', 'hash', ['ROLE_USER']),
            new InMemoryUser('bob', 'hash', ['ROLE_ADMIN']),
        ]);

        $user = $provider->loadUserByIdentifier('alice');

        $this->assertSame('alice', $user->getUserIdentifier());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testLoadUserThrowsOnMissing(): void
    {
        $provider = new InMemoryUserProvider();

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('unknown');
    }

    public function testAddUser(): void
    {
        $provider = new InMemoryUserProvider();
        $provider->addUser(new InMemoryUser('charlie', 'hash'));

        $user = $provider->loadUserByIdentifier('charlie');

        $this->assertSame('charlie', $user->getUserIdentifier());
    }

    public function testSupportsClass(): void
    {
        $provider = new InMemoryUserProvider();

        $this->assertTrue($provider->supportsClass(InMemoryUser::class));
        $this->assertFalse($provider->supportsClass(\stdClass::class));
    }
}
