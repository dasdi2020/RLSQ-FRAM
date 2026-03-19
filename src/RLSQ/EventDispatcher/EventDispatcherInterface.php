<?php

declare(strict_types=1);

namespace RLSQ\EventDispatcher;

interface EventDispatcherInterface
{
    /**
     * Dispatche un événement à tous ses listeners.
     */
    public function dispatch(object $event, ?string $eventName = null): object;

    /**
     * Ajoute un listener pour un événement donné.
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0): void;

    /**
     * Supprime un listener.
     */
    public function removeListener(string $eventName, callable $listener): void;

    /**
     * Ajoute un subscriber (enregistre tous ses listeners d'un coup).
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void;

    /**
     * Retourne les listeners pour un événement, triés par priorité.
     */
    public function getListeners(?string $eventName = null): array;

    /**
     * Vérifie si un événement a des listeners.
     */
    public function hasListeners(?string $eventName = null): bool;
}
