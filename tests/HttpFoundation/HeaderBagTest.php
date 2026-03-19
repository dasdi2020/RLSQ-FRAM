<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\HeaderBag;

class HeaderBagTest extends TestCase
{
    public function testGetIsCaseInsensitive(): void
    {
        $bag = new HeaderBag(['Content-Type' => 'text/html']);

        $this->assertSame('text/html', $bag->get('content-type'));
        $this->assertSame('text/html', $bag->get('Content-Type'));
        $this->assertSame('text/html', $bag->get('CONTENT-TYPE'));
    }

    public function testGetReturnsDefault(): void
    {
        $bag = new HeaderBag();

        $this->assertNull($bag->get('missing'));
        $this->assertSame('default', $bag->get('missing', 'default'));
    }

    public function testSetAndHas(): void
    {
        $bag = new HeaderBag();

        $this->assertFalse($bag->has('X-Custom'));

        $bag->set('X-Custom', 'value');

        $this->assertTrue($bag->has('x-custom'));
        $this->assertSame('value', $bag->get('X-Custom'));
    }

    public function testSetWithArray(): void
    {
        $bag = new HeaderBag();
        $bag->set('Accept', ['text/html', 'application/json']);

        $this->assertSame('text/html', $bag->get('Accept'));
    }

    public function testRemove(): void
    {
        $bag = new HeaderBag(['Host' => 'example.com']);

        $bag->remove('host');

        $this->assertFalse($bag->has('host'));
    }
}
