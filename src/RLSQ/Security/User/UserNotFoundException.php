<?php

declare(strict_types=1);

namespace RLSQ\Security\User;

class UserNotFoundException extends \RuntimeException
{
    public function __construct(string $identifier = '', ?\Throwable $previous = null)
    {
        $message = $identifier !== ''
            ? sprintf('Utilisateur "%s" introuvable.', $identifier)
            : 'Utilisateur introuvable.';

        parent::__construct($message, 0, $previous);
    }
}
