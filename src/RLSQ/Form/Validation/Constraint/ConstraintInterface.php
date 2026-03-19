<?php

declare(strict_types=1);

namespace RLSQ\Form\Validation\Constraint;

interface ConstraintInterface
{
    /**
     * Valide une valeur. Retourne null si valide, un message d'erreur sinon.
     */
    public function validate(mixed $value): ?string;
}
