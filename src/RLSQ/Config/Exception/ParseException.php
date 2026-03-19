<?php

declare(strict_types=1);

namespace RLSQ\Config\Exception;

class ParseException extends \RuntimeException
{
    public function __construct(string $message, ?string $file = null, ?int $line = null, ?\Throwable $previous = null)
    {
        if ($file !== null) {
            $message = sprintf('%s dans "%s"', $message, $file);
        }
        if ($line !== null) {
            $message .= sprintf(' à la ligne %d', $line);
        }

        parent::__construct($message, 0, $previous);
    }
}
