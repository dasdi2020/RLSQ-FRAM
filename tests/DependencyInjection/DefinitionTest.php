<?php

declare(strict_types=1);

namespace Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use RLSQ\DependencyInjection\Definition;
use RLSQ\DependencyInjection\Reference;

class DefinitionTest extends TestCase
{
    public function testClassAndArguments(): void
    {
        $def = new Definition(\stdClass::class, ['arg1', 'arg2']);

        $this->assertSame(\stdClass::class, $def->getClass());
        $this->assertSame(['arg1', 'arg2'], $def->getArguments());
    }

    public function testSetClass(): void
    {
        $def = new Definition();
        $def->setClass('App\\Service');

        $this->assertSame('App\\Service', $def->getClass());
    }

    public function testAddArgument(): void
    {
        $def = new Definition(\stdClass::class);
        $def->addArgument('first');
        $def->addArgument(new Reference('other'));

        $this->assertCount(2, $def->getArguments());
    }

    public function testSetArgument(): void
    {
        $def = new Definition(\stdClass::class, ['a', 'b', 'c']);
        $def->setArgument(1, 'B');

        $this->assertSame('B', $def->getArguments()[1]);
    }

    public function testMethodCalls(): void
    {
        $def = new Definition(\stdClass::class);
        $def->addMethodCall('setLogger', [new Reference('logger')]);
        $def->addMethodCall('setDebug', [true]);

        $calls = $def->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertSame('setLogger', $calls[0]['method']);
        $this->assertSame('setDebug', $calls[1]['method']);
    }

    public function testTags(): void
    {
        $def = new Definition(\stdClass::class);
        $def->addTag('kernel.event_listener', ['event' => 'kernel.request']);
        $def->addTag('kernel.event_listener', ['event' => 'kernel.response']);

        $this->assertTrue($def->hasTag('kernel.event_listener'));
        $this->assertFalse($def->hasTag('console.command'));
        $this->assertCount(2, $def->getTag('kernel.event_listener'));
    }

    public function testClearTag(): void
    {
        $def = new Definition(\stdClass::class);
        $def->addTag('my_tag');
        $def->clearTag('my_tag');

        $this->assertFalse($def->hasTag('my_tag'));
    }

    public function testSharedByDefault(): void
    {
        $def = new Definition(\stdClass::class);

        $this->assertTrue($def->isShared());

        $def->setShared(false);
        $this->assertFalse($def->isShared());
    }

    public function testAutowire(): void
    {
        $def = new Definition(\stdClass::class);
        $this->assertFalse($def->isAutowired());

        $def->setAutowired(true);
        $this->assertTrue($def->isAutowired());
    }

    public function testFactory(): void
    {
        $def = new Definition();
        $def->setFactory('App\\Factory', 'create');

        $this->assertSame(['App\\Factory', 'create'], $def->getFactory());
    }

    public function testPublic(): void
    {
        $def = new Definition(\stdClass::class);
        $this->assertTrue($def->isPublic());

        $def->setPublic(false);
        $this->assertFalse($def->isPublic());
    }
}
