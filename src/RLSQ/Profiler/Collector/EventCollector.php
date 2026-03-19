<?php

declare(strict_types=1);

namespace RLSQ\Profiler\Collector;

use RLSQ\EventDispatcher\EventDispatcherInterface;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\Profiler\DataCollectorInterface;

class EventCollector implements DataCollectorInterface
{
    private array $data = [];
    private array $dispatchedEvents = [];

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    /**
     * Enregistre un événement dispatché (appelé par le TraceableEventDispatcher).
     */
    public function logEvent(string $eventName, int $listenerCount): void
    {
        $this->dispatchedEvents[] = [
            'name' => $eventName,
            'listeners' => $listenerCount,
            'time' => microtime(true),
        ];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $allListeners = $this->dispatcher->getListeners();
        $totalListeners = 0;

        foreach ($allListeners as $listeners) {
            $totalListeners += count($listeners);
        }

        $this->data = [
            'dispatched_events' => $this->dispatchedEvents,
            'dispatched_count' => count($this->dispatchedEvents),
            'registered_listeners' => $totalListeners,
        ];
    }

    public function getName(): string
    {
        return 'events';
    }

    public function getData(): array
    {
        return $this->data;
    }
}
