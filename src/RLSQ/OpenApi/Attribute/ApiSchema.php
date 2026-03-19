<?php

declare(strict_types=1);

namespace RLSQ\OpenApi\Attribute;

/**
 * Définit un schéma OpenAPI sur une classe DTO/Entity.
 *
 * Les propriétés publiques de la classe sont automatiquement extraites.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ApiSchema
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}
