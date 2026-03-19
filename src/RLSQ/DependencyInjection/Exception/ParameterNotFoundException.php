<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection\Exception;

class ParameterNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $name, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Le paramètre "%s" n\'existe pas.', $name), 0, $previous);
    }
}
