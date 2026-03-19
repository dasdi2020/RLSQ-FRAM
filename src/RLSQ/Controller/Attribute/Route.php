<?php

declare(strict_types=1);

namespace RLSQ\Controller\Attribute;

/**
 * Attribut PHP 8 pour déclarer une route directement sur un contrôleur.
 *
 * Usage :
 *   #[Route('/article/{id}', name: 'article_show', methods: ['GET'])]
 *   public function show(int $id): Response { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $name = null,
        public readonly array $methods = [],
        public readonly array $requirements = [],
        public readonly array $defaults = [],
    ) {}
}
