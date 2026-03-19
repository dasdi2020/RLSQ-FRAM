<?php

declare(strict_types=1);

namespace RLSQ\Mailer;

use RLSQ\Mailer\Queue\QueueInterface;
use RLSQ\Mailer\Transport\TransportInterface;

/**
 * Façade principale pour l'envoi d'emails.
 * Supporte l'envoi immédiat et la mise en queue.
 */
class Mailer
{
    /** @var array<array{email: Email, status: string, sent_at: ?string, error: ?string}> */
    private array $log = [];

    private ?string $defaultFrom = null;

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly ?QueueInterface $queue = null,
    ) {}

    public function setDefaultFrom(string $from): void
    {
        $this->defaultFrom = $from;
    }

    /**
     * Envoie un email immédiatement.
     */
    public function send(Email $email): bool
    {
        $this->applyDefaults($email);

        try {
            $result = $this->transport->send($email);
            $this->log[] = [
                'email' => $email,
                'status' => $result ? 'sent' : 'failed',
                'sent_at' => date('Y-m-d H:i:s'),
                'error' => null,
            ];

            return $result;
        } catch (\Throwable $e) {
            $this->log[] = [
                'email' => $email,
                'status' => 'error',
                'sent_at' => null,
                'error' => $e->getMessage(),
            ];

            return false;
        }
    }

    /**
     * Met un email dans la file d'attente (sera envoyé par le worker).
     */
    public function queue(Email $email): void
    {
        if ($this->queue === null) {
            throw new \LogicException('Aucune queue configurée. Utilisez send() ou configurez une queue.');
        }

        $this->applyDefaults($email);
        $this->queue->enqueue($email);

        $this->log[] = [
            'email' => $email,
            'status' => 'queued',
            'sent_at' => null,
            'error' => null,
        ];
    }

    /**
     * Traite les emails en queue. Retourne le nombre d'emails envoyés.
     */
    public function processQueue(int $limit = 10): int
    {
        if ($this->queue === null) {
            return 0;
        }

        $sent = 0;

        for ($i = 0; $i < $limit; $i++) {
            $email = $this->queue->dequeue();

            if ($email === null) {
                break;
            }

            if ($this->send($email)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Retourne la queue (null si pas configurée).
     */
    public function getQueue(): ?QueueInterface
    {
        return $this->queue;
    }

    /**
     * Retourne le log des emails (envoyés, en queue, erreurs).
     *
     * @return array<array{email: Email, status: string, sent_at: ?string, error: ?string}>
     */
    public function getLog(): array
    {
        return $this->log;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    private function applyDefaults(Email $email): void
    {
        if ($email->getFrom() === null && $this->defaultFrom !== null) {
            $email->from($this->defaultFrom);
        }
    }
}
