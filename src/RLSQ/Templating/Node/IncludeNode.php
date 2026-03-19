<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

class IncludeNode implements NodeInterface
{
    public function __construct(
        public readonly string $template,
    ) {}

    public function compile(): string
    {
        $name = trim($this->template, '"\'');

        return 'echo $__engine->render(' . var_export($name, true) . ", \$__context);\n";
    }
}
