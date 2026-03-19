<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller\ValueResolver;

/**
 * Attribut pour mapper un paramètre de route à un champ d'entité.
 *
 * #[MapEntity(id: 'user_id')]
 * #[MapEntity(id: 'slug', field: 'slug')]
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapEntity
{
    public function __construct(
        public readonly string $id = 'id',
        public readonly ?string $field = null,
    ) {}
}
