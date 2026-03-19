<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly string $type = 'string',
        public readonly int $length = 255,
        public readonly bool $nullable = false,
        public readonly bool $unique = false,
    ) {}
}
