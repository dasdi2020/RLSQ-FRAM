<?php

declare(strict_types=1);

namespace Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use RLSQ\DependencyInjection\Compiler\CompilerPassInterface;
use RLSQ\DependencyInjection\ContainerBuilder;
use RLSQ\DependencyInjection\Definition;
use RLSQ\DependencyInjection\Exception\CircularReferenceException;
use RLSQ\DependencyInjection\Exception\ServiceNotFoundException;
use RLSQ\DependencyInjection\Parameter;
use RLSQ\DependencyInjection\Reference;

class ContainerBuilderTest extends TestCase
{
    // --- Register / Definitions ---

    public function testRegister(): void
    {
        $cb = new ContainerBuilder();
        $def = $cb->register('mailer', SimpleMailer::class);

        $this->assertInstanceOf(Definition::class, $def);
        $this->assertTrue($cb->hasDefinition('mailer'));
        $this->assertSame($def, $cb->getDefinition('mailer'));
    }

    public function testGetDefinitionThrowsOnMissing(): void
    {
        $cb = new ContainerBuilder();

        $this->expectException(ServiceNotFoundException::class);
        $cb->getDefinition('nope');
    }

    // --- Instanciation basique ---

    public function testGetInstantiatesService(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('mailer', SimpleMailer::class);

        $service = $cb->get('mailer');

        $this->assertInstanceOf(SimpleMailer::class, $service);
    }

    public function testSharedReturnsSameInstance(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('mailer', SimpleMailer::class);

        $a = $cb->get('mailer');
        $b = $cb->get('mailer');

        $this->assertSame($a, $b);
    }

    public function testNonSharedReturnsNewInstance(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('mailer', SimpleMailer::class)->setShared(false);

        $a = $cb->get('mailer');
        $b = $cb->get('mailer');

        $this->assertNotSame($a, $b);
    }

    // --- Arguments avec Reference ---

    public function testReferenceArgument(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('logger', SimpleLogger::class);
        $cb->register('app', AppService::class)
            ->setArguments([new Reference('logger')]);

        $app = $cb->get('app');

        $this->assertInstanceOf(AppService::class, $app);
        $this->assertInstanceOf(SimpleLogger::class, $app->logger);
    }

    // --- Arguments avec Parameter ---

    public function testParameterArgument(): void
    {
        $cb = new ContainerBuilder();
        $cb->setParameter('mailer.transport', 'smtp');
        $cb->register('mailer', ConfigurableMailer::class)
            ->setArguments([new Parameter('mailer.transport')]);

        $mailer = $cb->get('mailer');

        $this->assertSame('smtp', $mailer->transport);
    }

    public function testStringParameterResolution(): void
    {
        $cb = new ContainerBuilder();
        $cb->setParameter('db.host', '127.0.0.1');
        $cb->register('db', ConfigurableMailer::class)
            ->setArguments(['%db.host%']);

        $db = $cb->get('db');

        $this->assertSame('127.0.0.1', $db->transport);
    }

    // --- Method calls ---

    public function testMethodCalls(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('logger', SimpleLogger::class);
        $cb->register('app', AppService::class)
            ->setArguments([new Reference('logger')])
            ->addMethodCall('setDebug', [true]);

        $app = $cb->get('app');

        $this->assertTrue($app->debug);
    }

    // --- Autowiring ---

    public function testAutowiring(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('logger', SimpleLogger::class);
        $cb->register('app', AppService::class)->setAutowired(true);

        $cb->compile();

        $app = $cb->get('app');

        $this->assertInstanceOf(SimpleLogger::class, $app->logger);
    }

    public function testAutowiringByInterface(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('logger', SimpleLogger::class); // implémente LoggerInterface
        $cb->register('notifier', NotifierService::class)->setAutowired(true);

        $cb->compile();

        $notifier = $cb->get('notifier');

        $this->assertInstanceOf(SimpleLogger::class, $notifier->logger);
    }

    public function testAutowiringWithDefaultValue(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('optional', OptionalDepsService::class)->setAutowired(true);

        $cb->compile();

        $service = $cb->get('optional');

        $this->assertSame('default', $service->value);
        $this->assertNull($service->logger);
    }

    // --- Circular reference ---

    public function testCircularReferenceThrows(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('a', SimpleMailer::class)->setArguments([new Reference('b')]);
        $cb->register('b', SimpleMailer::class)->setArguments([new Reference('a')]);

        $this->expectException(CircularReferenceException::class);
        $cb->get('a');
    }

    // --- Tags ---

    public function testFindTaggedServiceIds(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('listener_a', SimpleLogger::class)
            ->addTag('kernel.event_listener', ['event' => 'kernel.request']);
        $cb->register('listener_b', SimpleLogger::class)
            ->addTag('kernel.event_listener', ['event' => 'kernel.response']);
        $cb->register('mailer', SimpleMailer::class); // pas de tag

        $tagged = $cb->findTaggedServiceIds('kernel.event_listener');

        $this->assertCount(2, $tagged);
        $this->assertArrayHasKey('listener_a', $tagged);
        $this->assertArrayHasKey('listener_b', $tagged);
    }

    // --- Compiler pass ---

    public function testCompilerPass(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('logger', SimpleLogger::class)
            ->addTag('app.collected');
        $cb->register('collector', CollectorService::class);

        $cb->addCompilerPass(new CollectTaggedPass());
        $cb->compile();

        $collector = $cb->get('collector');

        $this->assertSame(['logger'], $collector->collected);
    }

    // --- Alias ---

    public function testAlias(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('mailer', SimpleMailer::class);
        $cb->setAlias('mail', 'mailer');

        $this->assertTrue($cb->has('mail'));
        $this->assertSame($cb->get('mailer'), $cb->get('mail'));
    }

    // --- set() override ---

    public function testSetOverridesDefinition(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('mailer', SimpleMailer::class);

        $custom = new \stdClass();
        $cb->set('mailer', $custom);

        $this->assertSame($custom, $cb->get('mailer'));
    }

    // --- Factory ---

    public function testFactory(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('connection', \stdClass::class)
            ->setFactory(ConnectionFactory::class, 'create')
            ->setArguments(['localhost', 3306]);

        $conn = $cb->get('connection');

        $this->assertInstanceOf(\stdClass::class, $conn);
        $this->assertSame('localhost', $conn->host);
        $this->assertSame(3306, $conn->port);
    }

    // --- Remove definition ---

    public function testRemoveDefinition(): void
    {
        $cb = new ContainerBuilder();
        $cb->register('mailer', SimpleMailer::class);
        $cb->removeDefinition('mailer');

        $this->assertFalse($cb->hasDefinition('mailer'));
    }
}

// === Fixtures de test ===

interface LoggerInterface
{
    public function log(string $message): void;
}

class SimpleLogger implements LoggerInterface
{
    public function log(string $message): void {}
}

class SimpleMailer
{
    // Pas de constructeur — le plus simple
}

class ConfigurableMailer
{
    public function __construct(public readonly string $transport) {}
}

class AppService
{
    public bool $debug = false;

    public function __construct(public readonly SimpleLogger $logger) {}

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }
}

class NotifierService
{
    public function __construct(public readonly LoggerInterface $logger) {}
}

class OptionalDepsService
{
    public function __construct(
        public readonly string $value = 'default',
        public readonly ?LoggerInterface $logger = null,
    ) {}
}

class CollectorService
{
    public array $collected = [];

    public function setCollected(array $ids): void
    {
        $this->collected = $ids;
    }
}

class CollectTaggedPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $collectorDef = $container->getDefinition('collector');
        $ids = array_keys($container->findTaggedServiceIds('app.collected'));
        $collectorDef->addMethodCall('setCollected', [$ids]);
    }
}

class ConnectionFactory
{
    public static function create(string $host, int $port): \stdClass
    {
        $conn = new \stdClass();
        $conn->host = $host;
        $conn->port = $port;
        return $conn;
    }
}
