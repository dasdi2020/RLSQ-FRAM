<?php

declare(strict_types=1);

namespace RLSQ\Config\Definition;

interface NodeInterface
{
    public function getName(): string;

    /**
     * Valide et normalise une valeur.
     *
     * @throws \RLSQ\Config\Exception\ParseException
     */
    public function process(mixed $value): mixed;
}
