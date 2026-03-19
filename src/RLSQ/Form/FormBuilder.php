<?php

declare(strict_types=1);

namespace RLSQ\Form;

use RLSQ\Form\Validation\Constraint\ConstraintInterface;

class FormBuilder
{
    /** @var array<string, array{type: string, options: array}> */
    private array $fields = [];

    private string $method = 'POST';
    private ?string $action = null;

    public function __construct(
        private readonly string $name,
    ) {}

    /**
     * Ajoute un champ au formulaire.
     *
     * Options supportées :
     *   - label: string
     *   - required: bool
     *   - attr: array (attributs HTML)
     *   - constraints: ConstraintInterface[]
     *   - choices: array (pour ChoiceType)
     *   - data: mixed (valeur par défaut)
     */
    public function add(string $name, string $type = 'text', array $options = []): static
    {
        $this->fields[$name] = ['type' => $type, 'options' => $options];

        return $this;
    }

    public function setMethod(string $method): static
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return array<string, array{type: string, options: array}>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
