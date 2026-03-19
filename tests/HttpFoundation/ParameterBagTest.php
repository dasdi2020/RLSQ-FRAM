<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\ParameterBag;

class ParameterBagTest extends TestCase
{
    public function testGetReturnsValue(): void
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $this->assertSame('bar', $bag->get('foo'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $bag = new ParameterBag();

        $this->assertNull($bag->get('missing'));
        $this->assertSame('default', $bag->get('missing', 'default'));
    }

    public function testSetAndHas(): void
    {
        $bag = new ParameterBag();

        $this->assertFalse($bag->has('key'));

        $bag->set('key', 'value');

        $this->assertTrue($bag->has('key'));
        $this->assertSame('value', $bag->get('key'));
    }

    public function testRemove(): void
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $bag->remove('foo');

        $this->assertFalse($bag->has('foo'));
    }

    public function testAll(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $bag = new ParameterBag($data);

        $this->assertSame($data, $bag->all());
    }

    public function testKeys(): void
    {
        $bag = new ParameterBag(['x' => 1, 'y' => 2]);

        $this->assertSame(['x', 'y'], $bag->keys());
    }

    public function testCount(): void
    {
        $bag = new ParameterBag(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(3, $bag->count());
    }
}
