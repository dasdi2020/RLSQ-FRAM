<?php

declare(strict_types=1);

namespace RLSQ\Form\Validation\Constraint;

class Regex implements ConstraintInterface
{
    public function __construct(
        private readonly string $pattern,
        private readonly string $message = 'Cette valeur n\'est pas valide.',
    ) {}

    public function validate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!preg_match($this->pattern, (string) $value)) {
            return $this->message;
        }

        return null;
    }
}
