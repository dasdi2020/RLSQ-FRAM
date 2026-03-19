<?php

declare(strict_types=1);

namespace RLSQ\Form\Validation\Constraint;

class Length implements ConstraintInterface
{
    public function __construct(
        private readonly ?int $min = null,
        private readonly ?int $max = null,
        private readonly ?string $minMessage = null,
        private readonly ?string $maxMessage = null,
    ) {}

    public function validate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $length = mb_strlen((string) $value);

        if ($this->min !== null && $length < $this->min) {
            return $this->minMessage ?? sprintf('Ce champ doit contenir au moins %d caractères.', $this->min);
        }

        if ($this->max !== null && $length > $this->max) {
            return $this->maxMessage ?? sprintf('Ce champ ne peut pas dépasser %d caractères.', $this->max);
        }

        return null;
    }
}
