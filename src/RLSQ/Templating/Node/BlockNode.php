<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

class BlockNode implements NodeInterface
{
    /**
     * @param string          $name Nom du bloc
     * @param NodeInterface[] $body Contenu par défaut
     */
    public function __construct(
        public readonly string $name,
        public readonly array $body,
    ) {}

    public function compile(): string
    {
        $code = 'if (!isset($__blocks[\'' . $this->name . "'])) {\n";
        $code .= '$__blocks[\'' . $this->name . "'] = function() use (&\$__context, &\$__blocks) {\n";
        $code .= "extract(\$__context);\n";

        foreach ($this->body as $node) {
            $code .= $node->compile();
        }

        $code .= "};\n";
        $code .= "}\n";

        // Exécuter le bloc
        $code .= '$__blocks[\'' . $this->name . "']();\n";

        return $code;
    }
}
