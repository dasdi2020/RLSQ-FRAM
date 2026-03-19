<?php

declare(strict_types=1);

namespace RLSQ\OpenApi\Attribute;

/**
 * Métadonnées OpenAPI pour une route.
 *
 * #[ApiRoute(summary: 'Liste des articles', tags: ['Article'], responses: [200 => 'Liste JSON'])]
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class ApiRoute
{
    public function __construct(
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly array $tags = [],
        public readonly array $parameters = [],
        public readonly ?array $requestBody = null,
        public readonly array $responses = [],
        public readonly bool $deprecated = false,
    ) {}
}
