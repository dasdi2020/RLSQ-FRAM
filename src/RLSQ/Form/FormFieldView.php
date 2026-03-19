<?php

declare(strict_types=1);

namespace RLSQ\Form;

class FormFieldView
{
    public function __construct(
        public readonly string $name,
        public readonly string $fullName,
        public readonly string $type,
        public readonly mixed $value,
        public readonly string $label,
        public readonly array $attributes = [],
        public readonly array $errors = [],
        public readonly array $choices = [],
    ) {}

    public function render(): string
    {
        $html = '<div class="form-group">' . "\n";

        // Erreurs
        foreach ($this->errors as $error) {
            $html .= '  <div class="form-error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</div>\n";
        }

        // Label
        if ($this->type !== 'submit' && $this->type !== 'hidden') {
            $html .= sprintf(
                '  <label for="%s">%s</label>' . "\n",
                $this->fullName,
                htmlspecialchars($this->label, ENT_QUOTES, 'UTF-8'),
            );
        }

        // Input
        $html .= '  ' . $this->renderInput() . "\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderInput(): string
    {
        $attrs = $this->buildAttributes();
        $escapedValue = htmlspecialchars((string) ($this->value ?? ''), ENT_QUOTES, 'UTF-8');

        return match ($this->type) {
            'textarea' => sprintf('<textarea name="%s" id="%s"%s>%s</textarea>', $this->fullName, $this->fullName, $attrs, $escapedValue),
            'select' => $this->renderSelect($attrs),
            'submit' => sprintf('<button type="submit" name="%s"%s>%s</button>', $this->fullName, $attrs, htmlspecialchars($this->label, ENT_QUOTES, 'UTF-8')),
            default => sprintf('<input type="%s" name="%s" id="%s" value="%s"%s />', $this->type, $this->fullName, $this->fullName, $escapedValue, $attrs),
        };
    }

    private function renderSelect(string $attrs): string
    {
        $html = sprintf('<select name="%s" id="%s"%s>', $this->fullName, $this->fullName, $attrs);

        foreach ($this->choices as $choiceLabel => $choiceValue) {
            $selected = ((string) $this->value === (string) $choiceValue) ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars((string) $choiceValue, ENT_QUOTES, 'UTF-8'),
                $selected,
                htmlspecialchars((string) $choiceLabel, ENT_QUOTES, 'UTF-8'),
            );
        }

        $html .= '</select>';

        return $html;
    }

    private function buildAttributes(): string
    {
        $html = '';
        foreach ($this->attributes as $key => $val) {
            if ($val === true) {
                $html .= ' ' . $key;
            } elseif ($val !== false && $val !== null) {
                $html .= sprintf(' %s="%s"', $key, htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'));
            }
        }

        return $html;
    }
}
