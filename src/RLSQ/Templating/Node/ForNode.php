<?php

declare(strict_types=1);

namespace RLSQ\Templating\Node;

use RLSQ\Templating\ExpressionCompiler;

class ForNode implements NodeInterface
{
    /**
     * @param string          $valueName Variable d'itération (ex: "item")
     * @param string|null     $keyName   Variable de clé optionnelle (ex: "key")
     * @param string          $iterable  Expression itérable (ex: "items")
     * @param NodeInterface[] $body      Nœuds enfants
     * @param NodeInterface[] $elseBody  Nœuds pour le cas vide ({% else %})
     */
    public function __construct(
        public readonly string $valueName,
        public readonly ?string $keyName,
        public readonly string $iterable,
        public readonly array $body,
        public readonly array $elseBody = [],
    ) {}

    public function compile(): string
    {
        $iterableExpr = ExpressionCompiler::compile($this->iterable);
        $code = '';

        if (!empty($this->elseBody)) {
            $code .= '$__loop_items = ' . $iterableExpr . ";\n";
            $code .= "if (!empty(\$__loop_items)) {\n";
            $iterableExpr = '$__loop_items';
        }

        if ($this->keyName !== null) {
            $code .= 'foreach (' . $iterableExpr . ' as $' . $this->keyName . ' => $' . $this->valueName . ") {\n";
        } else {
            $code .= 'foreach (' . $iterableExpr . ' as $' . $this->valueName . ") {\n";
        }

        foreach ($this->body as $node) {
            $code .= $node->compile();
        }

        $code .= "}\n";

        if (!empty($this->elseBody)) {
            $code .= "} else {\n";
            foreach ($this->elseBody as $node) {
                $code .= $node->compile();
            }
            $code .= "}\n";
        }

        return $code;
    }
}
