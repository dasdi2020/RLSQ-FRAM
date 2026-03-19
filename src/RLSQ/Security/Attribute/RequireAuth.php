<?php

declare(strict_types=1);

namespace RLSQ\Security\Attribute;

/**
 * Exige qu'un utilisateur soit authentifié pour accéder à la route.
 *
 * #[RequireAuth]
 * #[RequireAuth(redirectTo: '/login')]
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class RequireAuth
{
    public function __construct(
        public readonly ?string $redirectTo = null,
        public readonly ?string $message = null,
    ) {}
}
