<?php

declare(strict_types=1);

namespace RLSQ\Security\Authorization\Voter;

use RLSQ\Security\Authentication\TokenInterface;

/**
 * Vote en fonction des rôles de l'utilisateur.
 * Ne vote que sur les attributs commençant par "ROLE_".
 */
class RoleVoter implements VoterInterface
{
    public function __construct(
        private readonly string $prefix = 'ROLE_',
    ) {}

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        $result = self::ACCESS_ABSTAIN;

        $roles = $token->getRoles();

        foreach ($attributes as $attribute) {
            if (!is_string($attribute) || !str_starts_with($attribute, $this->prefix)) {
                continue;
            }

            $result = self::ACCESS_DENIED;

            if (in_array($attribute, $roles, true)) {
                return self::ACCESS_GRANTED;
            }
        }

        return $result;
    }
}
