<?php

declare(strict_types=1);

namespace RLSQ\EventDispatcher;

/**
 * Classe de base pour tous les événements du framework.
 */
class Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
