<?php

declare(strict_types=1);

namespace RLSQ\EventDispatcher;

/**
 * Un événement dont la propagation peut être stoppée.
 * Inspiré de PSR-14.
 */
interface StoppableEventInterface
{
    public function isPropagationStopped(): bool;
}
