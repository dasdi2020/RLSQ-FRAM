<?php

declare(strict_types=1);

namespace RLSQ\EventDispatcher;

/**
 * Un subscriber déclare ses événements et méthodes via getSubscribedEvents().
 *
 * Retourne un tableau :
 *   - 'eventName' => 'methodName'
 *   - 'eventName' => ['methodName', priority]
 *   - 'eventName' => [['methodName1', priority], ['methodName2', priority]]
 */
interface EventSubscriberInterface
{
    public static function getSubscribedEvents(): array;
}
