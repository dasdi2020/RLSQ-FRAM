<?php

declare(strict_types=1);

namespace RLSQ\Database\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class GeneratedValue
{
    public function __construct(
        public readonly string $strategy = 'AUTO',
    ) {}
}
