<?php

declare(strict_types=1);

namespace RLSQ\EventDispatcher;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<int, array<callable>>>
     * Structure : [eventName => [priority => [callable, ...]]]
     */
    private array $listeners = [];

    /**
     * Cache des listeners triés par priorité (invalidé à chaque ajout/suppression).
     * @var array<string, array<callable>>
     */
    private array $sorted = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $eventName ??= $event::class;

        foreach ($this->getListeners($eventName) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $listener($event, $eventName, $this);
        }

        return $event;
    }

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority][] = $listener;
        unset($this->sorted[$eventName]);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $priority => &$listeners) {
            foreach ($listeners as $index => $registered) {
                if ($registered === $listener) {
                    unset($listeners[$index]);
                    $listeners = array_values($listeners);
                    unset($this->sorted[$eventName]);

                    if (empty($listeners)) {
                        unset($this->listeners[$eventName][$priority]);
                    }
                    if (empty($this->listeners[$eventName])) {
                        unset($this->listeners[$eventName]);
                    }

                    return;
                }
            }
        }
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $eventName => $params) {
            // 'eventName' => 'methodName'
            if (is_string($params)) {
                $this->addListener($eventName, [$subscriber, $params]);
                continue;
            }

            // 'eventName' => ['methodName', priority]
            if (is_string($params[0])) {
                $this->addListener(
                    $eventName,
                    [$subscriber, $params[0]],
                    $params[1] ?? 0,
                );
                continue;
            }

            // 'eventName' => [['methodName1', priority], ['methodName2', priority]]
            foreach ($params as $entry) {
                $this->addListener(
                    $eventName,
                    [$subscriber, $entry[0]],
                    $entry[1] ?? 0,
                );
            }
        }
    }

    public function getListeners(?string $eventName = null): array
    {
        if ($eventName !== null) {
            return $this->sortListeners($eventName);
        }

        // Retourne tous les listeners, triés, par événement
        $all = [];
        foreach (array_keys($this->listeners) as $name) {
            $all[$name] = $this->sortListeners($name);
        }

        return $all;
    }

    public function hasListeners(?string $eventName = null): bool
    {
        if ($eventName !== null) {
            return !empty($this->listeners[$eventName]);
        }

        return !empty($this->listeners);
    }

    /**
     * Trie les listeners par priorité décroissante (plus haute priorité = exécuté en premier).
     *
     * @return array<callable>
     */
    private function sortListeners(string $eventName): array
    {
        if (isset($this->sorted[$eventName])) {
            return $this->sorted[$eventName];
        }

        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        $listeners = $this->listeners[$eventName];
        krsort($listeners);

        $this->sorted[$eventName] = array_merge(...$listeners);

        return $this->sorted[$eventName];
    }
}
