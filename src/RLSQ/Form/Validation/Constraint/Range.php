<?php

declare(strict_types=1);

namespace RLSQ\Form\Validation\Constraint;

class Range implements ConstraintInterface
{
    public function __construct(
        private readonly int|float|null $min = null,
        private readonly int|float|null $max = null,
        private readonly ?string $minMessage = null,
        private readonly ?string $maxMessage = null,
    ) {}

    public function validate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = is_numeric($value) ? (float) $value : null;

        if ($numeric === null) {
            return 'Cette valeur doit être un nombre.';
        }

        if ($this->min !== null && $numeric < $this->min) {
            return $this->minMessage ?? sprintf('La valeur doit être au moins %s.', $this->min);
        }

        if ($this->max !== null && $numeric > $this->max) {
            return $this->maxMessage ?? sprintf('La valeur ne peut pas dépasser %s.', $this->max);
        }

        return null;
    }
}
