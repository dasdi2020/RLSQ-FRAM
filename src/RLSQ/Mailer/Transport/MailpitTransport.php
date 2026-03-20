<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Transport;

use RLSQ\Mailer\Email;

/**
 * Transport SMTP direct via socket. Compatible Mailpit, Mailtrap, etc.
 */
class MailpitTransport implements TransportInterface
{
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 1025,
    ) {}

    public function send(Email $email): bool
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);

        if (!$socket) {
            return false;
        }

        $this->read($socket);
        $this->write($socket, "EHLO localhost\r\n");
        $this->read($socket);

        $from = $email->getFrom() ?? 'noreply@localhost';
        $this->write($socket, "MAIL FROM:<{$from}>\r\n");
        $this->read($socket);

        foreach ($email->getTo() as $to) {
            $this->write($socket, "RCPT TO:<{$to}>\r\n");
            $this->read($socket);
        }

        foreach ($email->getCc() as $cc) {
            $this->write($socket, "RCPT TO:<{$cc}>\r\n");
            $this->read($socket);
        }

        $this->write($socket, "DATA\r\n");
        $this->read($socket);

        // Headers
        $headers = "From: {$from}\r\n";
        $headers .= "To: " . implode(', ', $email->getTo()) . "\r\n";
        if (!empty($email->getCc())) {
            $headers .= "Cc: " . implode(', ', $email->getCc()) . "\r\n";
        }
        $headers .= "Subject: " . ($email->getSubject() ?? '(sans sujet)') . "\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . $email->getId() . "@rlsq-fram>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if ($email->getHtml() !== null) {
            $boundary = '----=_Part_' . bin2hex(random_bytes(8));
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "\r\n";

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $body .= ($email->getText() ?? strip_tags($email->getHtml())) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $email->getHtml() . "\r\n";
            $body .= "--{$boundary}--\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "\r\n";
            $body = $email->getText() ?? '';
        }

        $this->write($socket, $headers . $body . "\r\n.\r\n");
        $this->read($socket);

        $this->write($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    public function getName(): string
    {
        return 'mailpit';
    }

    private function write($socket, string $data): void
    {
        fwrite($socket, $data);
    }

    private function read($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }
}
