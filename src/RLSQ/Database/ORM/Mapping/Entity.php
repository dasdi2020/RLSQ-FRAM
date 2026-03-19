<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    public function __construct(
        public readonly ?string $table = null,
    ) {}
}
