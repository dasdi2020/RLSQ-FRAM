<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    public function __construct(
        public readonly string $targetEntity,
        public readonly ?string $joinColumn = null,
    ) {}
}
