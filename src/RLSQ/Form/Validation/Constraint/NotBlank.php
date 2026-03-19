<?php

declare(strict_types=1);

namespace RLSQ\Form\Validation\Constraint;

class NotBlank implements ConstraintInterface
{
    public function __construct(
        private readonly string $message = 'Ce champ ne peut pas être vide.',
    ) {}

    public function validate(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return $this->message;
        }

        return null;
    }
}
