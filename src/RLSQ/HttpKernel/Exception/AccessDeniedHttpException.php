<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Exception;

class AccessDeniedHttpException extends HttpException
{
    public function __construct(string $message = 'Access Denied', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}
