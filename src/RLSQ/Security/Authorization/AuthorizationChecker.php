<?php

declare(strict_types=1);

namespace RLSQ\Security\Authorization;

use RLSQ\Security\Authentication\TokenStorage;

class AuthorizationChecker
{
    public function __construct(
        private readonly TokenStorage $tokenStorage,
        private readonly AccessDecisionManager $accessDecisionManager,
    ) {}

    public function isGranted(string|array $attributes, mixed $subject = null): bool
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return false;
        }

        $attributes = is_array($attributes) ? $attributes : [$attributes];

        return $this->accessDecisionManager->decide($token, $attributes, $subject);
    }
}
