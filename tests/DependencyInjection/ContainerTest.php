<?php

declare(strict_types=1);

namespace Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use RLSQ\DependencyInjection\Container;
use RLSQ\DependencyInjection\Exception\ParameterNotFoundException;
use RLSQ\DependencyInjection\Exception\ServiceNotFoundException;

class ContainerTest extends TestCase
{
    public function testSetAndGet(): void
    {
        $container = new Container();
        $service = new \stdClass();

        $container->set('my_service', $service);

        $this->assertTrue($container->has('my_service'));
        $this->assertSame($service, $container->get('my_service'));
    }

    public function testGetThrowsOnMissing(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFoundException::class);
        $container->get('missing');
    }

    public function testHasReturnsFalse(): void
    {
        $container = new Container();

        $this->assertFalse($container->has('nonexistent'));
    }

    public function testSelfReference(): void
    {
        $container = new Container();

        $this->assertSame($container, $container->get('service_container'));
    }

    public function testParameters(): void
    {
        $container = new Container();

        $container->setParameter('db.host', 'localhost');
        $container->setParameter('db.port', 3306);

        $this->assertTrue($container->hasParameter('db.host'));
        $this->assertSame('localhost', $container->getParameter('db.host'));
        $this->assertSame(3306, $container->getParameter('db.port'));
    }

    public function testGetParameterThrowsOnMissing(): void
    {
        $container = new Container();

        $this->expectException(ParameterNotFoundException::class);
        $container->getParameter('nope');
    }

    public function testAlias(): void
    {
        $container = new Container();
        $service = new \stdClass();

        $container->set('real_service', $service);
        $container->setAlias('alias', 'real_service');

        $this->assertTrue($container->has('alias'));
        $this->assertSame($service, $container->get('alias'));
    }
}
