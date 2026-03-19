<?php

declare(strict_types=1);

namespace RLSQ\Form;

/**
 * Représentation d'un formulaire prête pour le rendu HTML.
 */
class FormView
{
    /** @var FormFieldView[] */
    public array $children = [];

    public function __construct(
        public readonly string $name,
        public readonly string $method,
        public readonly ?string $action,
        public readonly array $errors = [],
    ) {}

    public function addChild(FormFieldView $child): void
    {
        $this->children[$child->name] = $child;
    }

    /**
     * Rend le formulaire en HTML.
     */
    public function render(): string
    {
        $html = sprintf(
            '<form name="%s" method="%s"%s>',
            $this->name,
            $this->method,
            $this->action !== null ? ' action="' . htmlspecialchars($this->action, ENT_QUOTES, 'UTF-8') . '"' : '',
        );
        $html .= "\n";

        foreach ($this->children as $child) {
            $html .= $child->render();
        }

        $html .= "</form>\n";

        return $html;
    }
}
