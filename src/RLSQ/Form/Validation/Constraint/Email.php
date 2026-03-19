<?php

declare(strict_types=1);

namespace RLSQ\Form\Validation\Constraint;

class Email implements ConstraintInterface
{
    public function __construct(
        private readonly string $message = 'Cette adresse email n\'est pas valide.',
    ) {}

    public function validate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $this->message;
        }

        return null;
    }
}
