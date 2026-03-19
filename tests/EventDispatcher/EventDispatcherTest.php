<?php

declare(strict_types=1);

namespace Tests\EventDispatcher;

use PHPUnit\Framework\TestCase;
use RLSQ\EventDispatcher\Event;
use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\EventDispatcher\EventSubscriberInterface;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    // --- addListener / hasListeners ---

    public function testHasNoListenersByDefault(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners());
        $this->assertFalse($this->dispatcher->hasListeners('some.event'));
    }

    public function testAddListener(): void
    {
        $this->dispatcher->addListener('test.event', function () {});

        $this->assertTrue($this->dispatcher->hasListeners('test.event'));
        $this->assertFalse($this->dispatcher->hasListeners('other.event'));
    }

    // --- dispatch ---

    public function testDispatchCallsListener(): void
    {
        $called = false;

        $this->dispatcher->addListener('test.event', function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new Event(), 'test.event');

        $this->assertTrue($called);
    }

    public function testDispatchPassesEventToListener(): void
    {
        $received = null;

        $this->dispatcher->addListener('test.event', function (Event $event) use (&$received) {
            $received = $event;
        });

        $event = new Event();
        $this->dispatcher->dispatch($event, 'test.event');

        $this->assertSame($event, $received);
    }

    public function testDispatchReturnsEvent(): void
    {
        $event = new Event();
        $result = $this->dispatcher->dispatch($event, 'test.event');

        $this->assertSame($event, $result);
    }

    public function testDispatchUsesClassNameWhenNoEventName(): void
    {
        $called = false;

        $this->dispatcher->addListener(Event::class, function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new Event());

        $this->assertTrue($called);
    }

    // --- Priorité ---

    public function testListenersCalledInPriorityOrder(): void
    {
        $order = [];

        $this->dispatcher->addListener('test', function () use (&$order) {
            $order[] = 'normal';
        }, 0);

        $this->dispatcher->addListener('test', function () use (&$order) {
            $order[] = 'high';
        }, 10);

        $this->dispatcher->addListener('test', function () use (&$order) {
            $order[] = 'low';
        }, -10);

        $this->dispatcher->dispatch(new Event(), 'test');

        $this->assertSame(['high', 'normal', 'low'], $order);
    }

    public function testSamePriorityPreservesInsertionOrder(): void
    {
        $order = [];

        $this->dispatcher->addListener('test', function () use (&$order) {
            $order[] = 'first';
        }, 0);

        $this->dispatcher->addListener('test', function () use (&$order) {
            $order[] = 'second';
        }, 0);

        $this->dispatcher->dispatch(new Event(), 'test');

        $this->assertSame(['first', 'second'], $order);
    }

    // --- stopPropagation ---

    public function testStopPropagation(): void
    {
        $order = [];

        $this->dispatcher->addListener('test', function (Event $event) use (&$order) {
            $order[] = 'first';
            $event->stopPropagation();
        }, 10);

        $this->dispatcher->addListener('test', function () use (&$order) {
            $order[] = 'second';
        }, 0);

        $this->dispatcher->dispatch(new Event(), 'test');

        $this->assertSame(['first'], $order);
    }

    // --- removeListener ---

    public function testRemoveListener(): void
    {
        $listener = function () {};

        $this->dispatcher->addListener('test', $listener);
        $this->assertTrue($this->dispatcher->hasListeners('test'));

        $this->dispatcher->removeListener('test', $listener);
        $this->assertFalse($this->dispatcher->hasListeners('test'));
    }

    public function testRemoveNonExistentListenerDoesNothing(): void
    {
        $this->dispatcher->removeListener('nope', function () {});

        $this->assertFalse($this->dispatcher->hasListeners('nope'));
    }

    // --- getListeners ---

    public function testGetListenersReturnsEmpty(): void
    {
        $this->assertSame([], $this->dispatcher->getListeners('none'));
    }

    public function testGetListenersSortedByPriority(): void
    {
        $a = function () {};
        $b = function () {};
        $c = function () {};

        $this->dispatcher->addListener('test', $a, -5);
        $this->dispatcher->addListener('test', $b, 10);
        $this->dispatcher->addListener('test', $c, 0);

        $listeners = $this->dispatcher->getListeners('test');

        $this->assertSame([$b, $c, $a], $listeners);
    }

    public function testGetAllListeners(): void
    {
        $this->dispatcher->addListener('event.a', function () {});
        $this->dispatcher->addListener('event.b', function () {});

        $all = $this->dispatcher->getListeners();

        $this->assertArrayHasKey('event.a', $all);
        $this->assertArrayHasKey('event.b', $all);
    }

    // --- Subscriber ---

    public function testAddSubscriber(): void
    {
        $subscriber = new TestSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        $this->assertTrue($this->dispatcher->hasListeners('event.simple'));
        $this->assertTrue($this->dispatcher->hasListeners('event.with_priority'));
        $this->assertTrue($this->dispatcher->hasListeners('event.multiple'));
    }

    public function testSubscriberMethodsCalled(): void
    {
        $subscriber = new TestSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        $this->dispatcher->dispatch(new Event(), 'event.simple');
        $this->assertSame(['onSimple'], $subscriber->calls);

        $subscriber->calls = [];
        $this->dispatcher->dispatch(new Event(), 'event.multiple');
        $this->assertSame(['onMultipleHigh', 'onMultipleLow'], $subscriber->calls);
    }
}

/**
 * Subscriber de test couvrant les 3 formats de getSubscribedEvents().
 */
class TestSubscriber implements EventSubscriberInterface
{
    public array $calls = [];

    public static function getSubscribedEvents(): array
    {
        return [
            // Format simple : 'eventName' => 'methodName'
            'event.simple' => 'onSimple',
            // Format avec priorité : 'eventName' => ['methodName', priority]
            'event.with_priority' => ['onWithPriority', 5],
            // Format multiple : 'eventName' => [['method1', prio], ['method2', prio]]
            'event.multiple' => [
                ['onMultipleHigh', 10],
                ['onMultipleLow', -10],
            ],
        ];
    }

    public function onSimple(Event $event): void
    {
        $this->calls[] = 'onSimple';
    }

    public function onWithPriority(Event $event): void
    {
        $this->calls[] = 'onWithPriority';
    }

    public function onMultipleHigh(Event $event): void
    {
        $this->calls[] = 'onMultipleHigh';
    }

    public function onMultipleLow(Event $event): void
    {
        $this->calls[] = 'onMultipleLow';
    }
}
