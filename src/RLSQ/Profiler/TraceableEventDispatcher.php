<?php

declare(strict_types=1);

namespace RLSQ\Profiler;

use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\Profiler\Collector\EventCollector;

/**
 * EventDispatcher qui trace les événements dispatchés pour le profiler.
 */
class TraceableEventDispatcher extends EventDispatcher
{
    private ?EventCollector $eventCollector = null;

    public function setEventCollector(EventCollector $collector): void
    {
        $this->eventCollector = $collector;
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $eventName ??= $event::class;

        if ($this->eventCollector !== null) {
            $listenerCount = count($this->getListeners($eventName));
            $this->eventCollector->logEvent($eventName, $listenerCount);
        }

        return parent::dispatch($event, $eventName);
    }
}
