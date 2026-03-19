<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Transport;

use RLSQ\Mailer\Email;

/**
 * Transport SMTP via la fonction mail() de PHP.
 * Pour la production, utiliser un vrai serveur SMTP avec sockets.
 */
class SmtpTransport implements TransportInterface
{
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 25,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly ?string $encryption = null,
    ) {}

    public function send(Email $email): bool
    {
        $to = implode(', ', $email->getTo());
        $subject = $email->getSubject() ?? '(sans sujet)';

        $headers = [];
        if ($email->getFrom() !== null) {
            $headers[] = 'From: ' . $email->getFrom();
        }
        if (!empty($email->getCc())) {
            $headers[] = 'Cc: ' . implode(', ', $email->getCc());
        }
        if (!empty($email->getBcc())) {
            $headers[] = 'Bcc: ' . implode(', ', $email->getBcc());
        }
        if ($email->getReplyTo() !== null) {
            $headers[] = 'Reply-To: ' . $email->getReplyTo();
        }

        if ($email->getHtml() !== null) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = $email->getHtml();
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body = $email->getText() ?? '';
        }

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    public function getName(): string
    {
        return 'smtp';
    }
}
