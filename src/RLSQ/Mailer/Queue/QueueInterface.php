<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Queue;

use RLSQ\Mailer\Email;

interface QueueInterface
{
    public function enqueue(Email $email): void;

    public function dequeue(): ?Email;

    public function count(): int;

    /** @return Email[] */
    public function peek(int $limit = 10): array;

    public function clear(): void;
}
