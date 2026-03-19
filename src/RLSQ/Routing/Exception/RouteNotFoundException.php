<?php

declare(strict_types=1);

namespace RLSQ\Routing\Exception;

class RouteNotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Aucune route ne correspond.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
