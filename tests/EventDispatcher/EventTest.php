<?php

declare(strict_types=1);

namespace Tests\EventDispatcher;

use PHPUnit\Framework\TestCase;
use RLSQ\EventDispatcher\Event;

class EventTest extends TestCase
{
    public function testPropagationNotStoppedByDefault(): void
    {
        $event = new Event();

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagation(): void
    {
        $event = new Event();
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}
