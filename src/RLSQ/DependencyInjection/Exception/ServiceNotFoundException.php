<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection\Exception;

class ServiceNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $id, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Le service "%s" n\'existe pas.', $id), 0, $previous);
    }
}
