<?php

declare(strict_types=1);

namespace RLSQ\Security\Attribute;

/**
 * Restreint l'accès à une méthode/classe de contrôleur par rôle ou permission.
 *
 * #[IsGranted('ROLE_ADMIN')]
 * #[IsGranted('ROLE_EDITOR', message: 'Accès éditeur requis.')]
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class IsGranted
{
    public function __construct(
        public readonly string $attribute,
        public readonly ?string $message = null,
        public readonly int $statusCode = 403,
    ) {}
}
