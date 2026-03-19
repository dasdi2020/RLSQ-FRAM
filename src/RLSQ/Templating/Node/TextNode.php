<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

class TextNode implements NodeInterface
{
    public function __construct(
        public readonly string $text,
    ) {}

    public function compile(): string
    {
        if ($this->text === '') {
            return '';
        }

        return 'echo ' . var_export($this->text, true) . ";\n";
    }
}
