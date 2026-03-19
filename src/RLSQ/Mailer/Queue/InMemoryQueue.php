<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Queue;

use RLSQ\Mailer\Email;

/**
 * Queue en mémoire. Utile pour les tests.
 */
class InMemoryQueue implements QueueInterface
{
    /** @var Email[] */
    private array $emails = [];

    public function enqueue(Email $email): void
    {
        $this->emails[] = $email;

        // Trier par priorité
        usort($this->emails, fn (Email $a, Email $b) => $a->getPriority() <=> $b->getPriority());
    }

    public function dequeue(): ?Email
    {
        if (empty($this->emails)) {
            return null;
        }

        return array_shift($this->emails);
    }

    public function count(): int
    {
        return count($this->emails);
    }

    public function peek(int $limit = 10): array
    {
        return array_slice($this->emails, 0, $limit);
    }

    public function clear(): void
    {
        $this->emails = [];
    }
}
