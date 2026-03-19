<?php

declare(strict_types=1);

namespace RLSQ\Templating;

class Token
{
    public const TEXT = 'TEXT';
    public const PRINT = 'PRINT';       // {{ expr }}
    public const TAG_OPEN = 'TAG_OPEN'; // {% tag ... %}
    public const COMMENT = 'COMMENT';   // {# ... #}

    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly int $line,
    ) {}
}
