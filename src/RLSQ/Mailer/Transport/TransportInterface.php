<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Transport;

use RLSQ\Mailer\Email;

interface TransportInterface
{
    /**
     * Envoie un email. Retourne true si envoyé avec succès.
     */
    public function send(Email $email): bool;

    public function getName(): string;
}
