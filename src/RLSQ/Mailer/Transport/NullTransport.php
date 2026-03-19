<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Transport;

use RLSQ\Mailer\Email;

/**
 * Ne fait rien. Utile pour le dev/test.
 */
class NullTransport implements TransportInterface
{
    public function send(Email $email): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'null';
    }
}
