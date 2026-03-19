<?php

declare(strict_types=1);

namespace RLSQ\Form;

use RLSQ\Form\Validation\Constraint\ConstraintInterface;
use RLSQ\HttpFoundation\Request;

class Form implements FormInterface
{
    private bool $submitted = false;
    private mixed $data;
    /** @var array<string, string[]> fieldName => errors */
    private array $errors = [];

    /**
     * @param array<string, array{type: string, options: array}> $fields
     */
    public function __construct(
        private readonly string $name,
        private readonly string $method,
        private readonly ?string $action,
        private readonly array $fields,
        mixed $initialData = null,
    ) {
        $this->data = $initialData;
    }

    public function handleRequest(Request $request): void
    {
        // Ne traiter que si la méthode correspond
        if (!$request->isMethod($this->method)) {
            return;
        }

        $this->submitted = true;

        // Récupérer les données depuis la Request
        $source = match ($this->method) {
            'GET' => $request->query->all(),
            default => $request->request->all(),
        };

        // Les données du formulaire peuvent être dans un sous-tableau nommé
        $formData = $source[$this->name] ?? $source;

        // Binding : si data est un objet, hydrater ses propriétés
        if (is_object($this->data)) {
            $this->bindToObject($formData);
        } elseif (is_array($this->data)) {
            $this->data = array_merge($this->data, $formData);
        } else {
            $this->data = $formData;
        }

        // Validation
        $this->validate($formData);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function isValid(): bool
    {
        return $this->submitted && empty($this->errors);
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function createView(): FormView
    {
        $view = new FormView($this->name, $this->method, $this->action, $this->errors['_form'] ?? []);

        foreach ($this->fields as $fieldName => $field) {
            $type = $this->resolveHtmlType($field['type']);
            $options = $field['options'];
            $label = $options['label'] ?? $this->humanize($fieldName);
            $value = $this->getFieldValue($fieldName);

            $attrs = $options['attr'] ?? [];
            if ($options['required'] ?? false) {
                $attrs['required'] = true;
            }

            $view->addChild(new FormFieldView(
                name: $fieldName,
                fullName: $this->name . '[' . $fieldName . ']',
                type: $type,
                value: $value,
                label: $label,
                attributes: $attrs,
                errors: $this->errors[$fieldName] ?? [],
                choices: $options['choices'] ?? [],
            ));
        }

        return $view;
    }

    private function validate(array $formData): void
    {
        $this->errors = [];

        foreach ($this->fields as $fieldName => $field) {
            $constraints = $field['options']['constraints'] ?? [];
            $value = $formData[$fieldName] ?? null;

            foreach ($constraints as $constraint) {
                if (!$constraint instanceof ConstraintInterface) {
                    continue;
                }

                $error = $constraint->validate($value);
                if ($error !== null) {
                    $this->errors[$fieldName][] = $error;
                }
            }
        }
    }

    private function bindToObject(array $formData): void
    {
        $ref = new \ReflectionClass($this->data);

        foreach ($formData as $field => $value) {
            if (!$ref->hasProperty($field)) {
                continue;
            }

            $prop = $ref->getProperty($field);
            $type = $prop->getType();

            // Cast basique
            if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                $value = match ($type->getName()) {
                    'int' => (int) $value,
                    'float' => (float) $value,
                    'bool' => (bool) $value,
                    default => $value,
                };
            }

            $prop->setValue($this->data, $value);
        }
    }

    private function getFieldValue(string $fieldName): mixed
    {
        if (is_object($this->data)) {
            $ref = new \ReflectionClass($this->data);
            if ($ref->hasProperty($fieldName)) {
                $prop = $ref->getProperty($fieldName);
                if ($prop->isInitialized($this->data)) {
                    return $prop->getValue($this->data);
                }
            }
            return null;
        }

        if (is_array($this->data)) {
            return $this->data[$fieldName] ?? null;
        }

        return null;
    }

    private function resolveHtmlType(string $type): string
    {
        return match ($type) {
            'text', 'TextType' => 'text',
            'email', 'EmailType' => 'email',
            'password', 'PasswordType' => 'password',
            'integer', 'IntegerType', 'number' => 'number',
            'textarea', 'TextareaType' => 'textarea',
            'choice', 'ChoiceType', 'select' => 'select',
            'hidden', 'HiddenType' => 'hidden',
            'submit', 'SubmitType' => 'submit',
            default => $type,
        };
    }

    private function humanize(string $text): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $text));
    }
}
