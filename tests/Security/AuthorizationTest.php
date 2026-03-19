<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use RLSQ\Security\Authentication\TokenStorage;
use RLSQ\Security\Authentication\UsernamePasswordToken;
use RLSQ\Security\Authorization\AccessDecisionManager;
use RLSQ\Security\Authorization\AuthorizationChecker;
use RLSQ\Security\Authorization\Voter\RoleVoter;
use RLSQ\Security\Authorization\Voter\VoterInterface;
use RLSQ\Security\User\InMemoryUser;

class AuthorizationTest extends TestCase
{
    // --- RoleVoter ---

    public function testRoleVoterGrants(): void
    {
        $voter = new RoleVoter();
        $token = $this->makeToken(['ROLE_ADMIN', 'ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['ROLE_ADMIN']));
    }

    public function testRoleVoterDenies(): void
    {
        $voter = new RoleVoter();
        $token = $this->makeToken(['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['ROLE_ADMIN']));
    }

    public function testRoleVoterAbstains(): void
    {
        $voter = new RoleVoter();
        $token = $this->makeToken(['ROLE_USER']);

        // Attribut non ROLE_ → abstention
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['EDIT']));
    }

    // --- AccessDecisionManager ---

    public function testAffirmativeStrategy(): void
    {
        $manager = new AccessDecisionManager([new RoleVoter()], AccessDecisionManager::STRATEGY_AFFIRMATIVE);

        $admin = $this->makeToken(['ROLE_ADMIN']);
        $user = $this->makeToken(['ROLE_USER']);

        $this->assertTrue($manager->decide($admin, ['ROLE_ADMIN']));
        $this->assertFalse($manager->decide($user, ['ROLE_ADMIN']));
    }

    public function testUnanimousStrategy(): void
    {
        $grantVoter = new class implements VoterInterface {
            public function vote($token, $subject, array $attrs): int { return self::ACCESS_GRANTED; }
        };
        $denyVoter = new class implements VoterInterface {
            public function vote($token, $subject, array $attrs): int { return self::ACCESS_DENIED; }
        };

        $manager = new AccessDecisionManager([$grantVoter, $denyVoter], AccessDecisionManager::STRATEGY_UNANIMOUS);
        $token = $this->makeToken(['ROLE_USER']);

        $this->assertFalse($manager->decide($token, ['anything']));
    }

    public function testConsensusStrategy(): void
    {
        $grantVoter1 = new class implements VoterInterface {
            public function vote($token, $subject, array $attrs): int { return self::ACCESS_GRANTED; }
        };
        $grantVoter2 = new class implements VoterInterface {
            public function vote($token, $subject, array $attrs): int { return self::ACCESS_GRANTED; }
        };
        $denyVoter = new class implements VoterInterface {
            public function vote($token, $subject, array $attrs): int { return self::ACCESS_DENIED; }
        };

        $manager = new AccessDecisionManager(
            [$grantVoter1, $grantVoter2, $denyVoter],
            AccessDecisionManager::STRATEGY_CONSENSUS,
        );

        $token = $this->makeToken(['ROLE_USER']);

        // 2 grants vs 1 deny → granted
        $this->assertTrue($manager->decide($token, ['anything']));
    }

    // --- AuthorizationChecker ---

    public function testAuthorizationChecker(): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($this->makeToken(['ROLE_USER', 'ROLE_ADMIN']));

        $checker = new AuthorizationChecker(
            $tokenStorage,
            new AccessDecisionManager([new RoleVoter()]),
        );

        $this->assertTrue($checker->isGranted('ROLE_USER'));
        $this->assertTrue($checker->isGranted('ROLE_ADMIN'));
        $this->assertFalse($checker->isGranted('ROLE_SUPER_ADMIN'));
    }

    public function testAuthorizationCheckerWithoutToken(): void
    {
        $tokenStorage = new TokenStorage();

        $checker = new AuthorizationChecker(
            $tokenStorage,
            new AccessDecisionManager([new RoleVoter()]),
        );

        $this->assertFalse($checker->isGranted('ROLE_USER'));
    }

    // --- Helper ---

    private function makeToken(array $roles): UsernamePasswordToken
    {
        return new UsernamePasswordToken(
            new InMemoryUser('testuser', null, $roles),
            $roles,
        );
    }
}
