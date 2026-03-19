<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Transport;

use RLSQ\Mailer\Email;

/**
 * Écrit les emails dans un fichier log. Utile pour le dev.
 */
class LogTransport implements TransportInterface
{
    public function __construct(
        private readonly string $logDir,
    ) {}

    public function send(Email $email): bool
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }

        $filename = date('Y-m-d_H-i-s') . '_' . $email->getId() . '.eml';
        $path = $this->logDir . DIRECTORY_SEPARATOR . $filename;

        $content = "ID: {$email->getId()}\n";
        $content .= "Date: {$email->getCreatedAt()->format('Y-m-d H:i:s')}\n";
        $content .= "From: {$email->getFrom()}\n";
        $content .= "To: " . implode(', ', $email->getTo()) . "\n";
        if (!empty($email->getCc())) {
            $content .= "Cc: " . implode(', ', $email->getCc()) . "\n";
        }
        $content .= "Subject: {$email->getSubject()}\n";
        $content .= "Priority: {$email->getPriority()}\n";
        $content .= "\n--- TEXT ---\n" . ($email->getText() ?? '') . "\n";
        if ($email->getHtml() !== null) {
            $content .= "\n--- HTML ---\n" . $email->getHtml() . "\n";
        }

        return file_put_contents($path, $content) !== false;
    }

    public function getName(): string
    {
        return 'log';
    }
}
