<?php

declare(strict_types=1);

namespace RLSQ\Security\Authorization\Voter;

use RLSQ\Security\Authentication\TokenInterface;

interface VoterInterface
{
    public const ACCESS_GRANTED = 1;
    public const ACCESS_ABSTAIN = 0;
    public const ACCESS_DENIED = -1;

    /**
     * @param string[] $attributes
     */
    public function vote(TokenInterface $token, mixed $subject, array $attributes): int;
}
