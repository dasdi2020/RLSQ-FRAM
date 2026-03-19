<?php

declare(strict_types=1);

namespace RLSQ\Routing\Exception;

class MethodNotAllowedException extends \RuntimeException
{
    /** @var string[] */
    private array $allowedMethods;

    /**
     * @param string[] $allowedMethods
     */
    public function __construct(array $allowedMethods, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->allowedMethods = $allowedMethods;

        if ($message === '') {
            $message = sprintf('Méthode HTTP non autorisée. Méthodes acceptées : %s.', implode(', ', $allowedMethods));
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
