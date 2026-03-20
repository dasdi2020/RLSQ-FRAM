<?php

declare(strict_types=1);

namespace RLSQ;

use RLSQ\DependencyInjection\ContainerBuilder;
use RLSQ\DependencyInjection\Reference;
use RLSQ\Dotenv\Dotenv;
use RLSQ\EventDispatcher\EventDispatcherInterface;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\HttpKernel\HttpKernelInterface;

/**
 * Kernel applicatif RLSQ-FRAM.
 * Charge la config, construit le container DI, et fournit le HttpKernel.
 */
class Kernel
{
    private ContainerBuilder $container;
    private bool $booted = false;
    private string $projectDir;
    private string $environment;
    private bool $debug;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;

        // Charger .env
        Dotenv::loadIn($projectDir);

        $this->environment = $_ENV['APP_ENV'] ?? 'dev';
        $this->debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->container = new ContainerBuilder();
        $this->registerParameters();
        $this->registerCoreServices();
        $this->registerApplicationServices();
        $this->container->compile();
        $this->registerEventSubscribers();

        $this->booted = true;
    }

    public function handle(Request $request): Response
    {
        $this->boot();

        return $this->getHttpKernel()->handle($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->getHttpKernel()->terminate($request, $response);
    }

    public function getContainer(): ContainerBuilder
    {
        $this->boot();

        return $this->container;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    private function registerParameters(): void
    {
        $this->container->setParameter('kernel.project_dir', $this->projectDir);
        $this->container->setParameter('kernel.environment', $this->environment);
        $this->container->setParameter('kernel.debug', $this->debug);
        $this->container->setParameter('kernel.cache_dir', $this->projectDir . '/var/cache/' . $this->environment);
        $this->container->setParameter('kernel.log_dir', $this->projectDir . '/var/log');

        // Paramètres depuis .env
        $envParams = [
            'app.secret' => $_ENV['APP_SECRET'] ?? 'change_me',
            'app.url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
            'database.driver' => $_ENV['DATABASE_DRIVER'] ?? 'sqlite',
            'database.path' => $this->resolvePath($_ENV['DATABASE_PATH'] ?? 'var/db.sqlite'),
            'database.host' => $_ENV['DATABASE_HOST'] ?? 'localhost',
            'database.port' => $_ENV['DATABASE_PORT'] ?? '3306',
            'database.name' => $_ENV['DATABASE_NAME'] ?? 'rlsq',
            'database.user' => $_ENV['DATABASE_USER'] ?? 'root',
            'database.password' => $_ENV['DATABASE_PASSWORD'] ?? '',
            'mailer.dsn' => $_ENV['MAILER_DSN'] ?? 'null://null',
            'mailer.from' => $_ENV['MAILER_FROM'] ?? 'noreply@rlsq-fram.local',
            'jwt.secret' => $_ENV['JWT_SECRET'] ?? ($_ENV['APP_SECRET'] ?? 'change_me'),
            'jwt.ttl' => (int) ($_ENV['JWT_TTL'] ?? '900'),
            'jwt.refresh_ttl' => (int) ($_ENV['JWT_REFRESH_TTL'] ?? '604800'),
        ];

        foreach ($envParams as $key => $value) {
            $this->container->setParameter($key, $value);
        }
    }

    private function registerCoreServices(): void
    {
        $c = $this->container;

        // Database — on instancie directement car les %param% dans les arrays nested ne sont pas résolus par le container
        $dbConfig = [
            'driver' => $this->container->getParameter('database.driver'),
            'path' => $this->container->getParameter('database.path'),
            'host' => $this->container->getParameter('database.host'),
            'port' => $this->container->getParameter('database.port'),
            'dbname' => $this->container->getParameter('database.name'),
            'user' => $this->container->getParameter('database.user'),
            'password' => $this->container->getParameter('database.password'),
        ];
        $c->set('database.connection', \RLSQ\Database\Connection::create($dbConfig));
        $c->setAlias(\RLSQ\Database\Connection::class, 'database.connection');

        // EntityManager
        $c->register('entity_manager', \RLSQ\Database\ORM\EntityManager::class)
            ->setArguments([new Reference('database.connection')]);
        $c->setAlias(\RLSQ\Database\ORM\EntityManager::class, 'entity_manager');

        // Event Dispatcher
        $c->register('event_dispatcher', \RLSQ\Profiler\TraceableEventDispatcher::class);
        $c->setAlias(EventDispatcherInterface::class, 'event_dispatcher');
        $c->setAlias(\RLSQ\EventDispatcher\EventDispatcher::class, 'event_dispatcher');

        // Profiler
        $c->register('profiler', \RLSQ\Profiler\Profiler::class);

        // Routing
        $c->register('route_collection', \RLSQ\Routing\RouteCollection::class);
        $c->setAlias(\RLSQ\Routing\RouteCollection::class, 'route_collection');

        $c->register('url_matcher', \RLSQ\Routing\Matcher\UrlMatcher::class)
            ->setArguments([new Reference('route_collection')]);

        $c->register('url_generator', \RLSQ\Routing\Generator\UrlGenerator::class)
            ->setArguments([new Reference('route_collection')]);
        $c->setAlias(\RLSQ\Routing\Generator\UrlGeneratorInterface::class, 'url_generator');

        // Security
        $c->register('token_storage', \RLSQ\Security\Authentication\TokenStorage::class);
        $c->setAlias(\RLSQ\Security\Authentication\TokenStorage::class, 'token_storage');

        // JWT
        $c->register('jwt_manager', \RLSQ\Security\Jwt\JwtManager::class)
            ->setArguments(['%jwt.secret%', '%jwt.ttl%', '%jwt.refresh_ttl%']);
        $c->setAlias(\RLSQ\Security\Jwt\JwtManager::class, 'jwt_manager');

        // Password Hasher
        $c->register('password_hasher', \RLSQ\Security\Hasher\NativePasswordHasher::class);
        $c->setAlias(\RLSQ\Security\Hasher\PasswordHasherInterface::class, 'password_hasher');

        // Controller Resolver
        $c->register('argument_resolver', \RLSQ\HttpKernel\Controller\ArgumentResolver::class)
            ->setArguments([[
                new Reference('entity_value_resolver'),
                new Reference('service_value_resolver'),
            ]]);

        $c->register('entity_value_resolver', \RLSQ\HttpKernel\Controller\ValueResolver\EntityValueResolver::class)
            ->setArguments([new Reference('entity_manager')]);

        $c->register('service_value_resolver', \RLSQ\HttpKernel\Controller\ValueResolver\ServiceValueResolver::class)
            ->setArguments([new Reference('service_container')]);

        $c->register('controller_resolver', \RLSQ\HttpKernel\Controller\ControllerResolver::class)
            ->setArguments([new Reference('service_container')]);

        // HttpKernel
        $c->register('http_kernel', HttpKernel::class)
            ->setArguments([
                new Reference('event_dispatcher'),
                new Reference('controller_resolver'),
                new Reference('argument_resolver'),
            ]);
        $c->setAlias(HttpKernelInterface::class, 'http_kernel');

        // Mailer — auto-detect Mailpit (SMTP localhost:1025), sinon fallback LogTransport
        $mailerHost = $_ENV['MAILER_HOST'] ?? 'localhost';
        $mailerPort = (int) ($_ENV['MAILER_PORT'] ?? 1025);
        $smtpCheck = @fsockopen($mailerHost, $mailerPort, $errno, $errstr, 1);

        if ($smtpCheck) {
            fclose($smtpCheck);
            $c->set('mailer.transport', new \RLSQ\Mailer\Transport\MailpitTransport($mailerHost, $mailerPort));
        } else {
            $c->set('mailer.transport', new \RLSQ\Mailer\Transport\LogTransport($this->projectDir . '/var/mail_log'));
        }

        $c->register('mailer.queue', \RLSQ\Mailer\Queue\FilesystemQueue::class)
            ->setArguments(['%kernel.project_dir%/var/mail_queue']);

        $c->register('mailer', \RLSQ\Mailer\Mailer::class)
            ->setArguments([new Reference('mailer.transport'), new Reference('mailer.queue')])
            ->addMethodCall('setDefaultFrom', ['%mailer.from%']);
        $c->setAlias(\RLSQ\Mailer\Mailer::class, 'mailer');

        // Templating
        $c->register('templating', \RLSQ\Templating\Engine::class)
            ->setArguments([
                new Reference('template_loader'),
                '%kernel.cache_dir%/templates',
            ]);
        $c->setAlias(\RLSQ\Templating\EngineInterface::class, 'templating');

        $c->register('template_loader', \RLSQ\Templating\Loader\FilesystemLoader::class)
            ->setArguments(['%kernel.project_dir%/templates']);

        // Profiler Collectors
        $c->register('collector.request', \RLSQ\Profiler\Collector\RequestCollector::class);
        $c->register('collector.route', \RLSQ\Profiler\Collector\RouteCollector::class);
        $c->register('collector.performance', \RLSQ\Profiler\Collector\PerformanceCollector::class)
            ->setArguments([new Reference('profiler')]);
        $c->register('collector.event', \RLSQ\Profiler\Collector\EventCollector::class)
            ->setArguments([new Reference('event_dispatcher')]);
        $c->register('collector.mailer', \RLSQ\Profiler\Collector\MailerCollector::class)
            ->setArguments([new Reference('mailer')]);
    }

    private function registerApplicationServices(): void
    {
        $c = $this->container;
        $projectDir = $this->projectDir;

        // Tenant system
        $c->set('tenant.context', new \App\Tenant\TenantContext());
        $c->setAlias(\App\Tenant\TenantContext::class, 'tenant.context');

        $c->set('tenant.resolver', new \App\Tenant\TenantResolver($c->get('database.connection')));
        $c->setAlias(\App\Tenant\TenantResolver::class, 'tenant.resolver');

        $c->set('tenant.connection_factory', new \App\Tenant\Database\TenantConnectionFactory($projectDir));
        $c->setAlias(\App\Tenant\Database\TenantConnectionFactory::class, 'tenant.connection_factory');

        $provisioner = new \App\Tenant\Database\TenantDatabaseProvisioner($projectDir);
        $provisioner->addBaseMigration(new \App\Tenant\Database\TenantBaseMigration());
        $provisioner->addBaseMigration(new \App\Tenant\Database\TenantMetaSchemaMigration());
        $provisioner->addBaseMigration(new \App\Tenant\Database\TenantDashboardMigration());
        $provisioner->addBaseMigration(new \App\Tenant\Database\TenantFormsMigration());
        $provisioner->addBaseMigration(new \App\Tenant\Database\TenantPagesMigration());
        $provisioner->addBaseMigration(new \App\Tenant\Database\TenantEmbedMigration());
        $c->set('tenant.provisioner', $provisioner);
        $c->setAlias(\App\Tenant\Database\TenantDatabaseProvisioner::class, 'tenant.provisioner');

        $c->set('tenant.service', new \App\Tenant\TenantService(
            $c->get('database.connection'),
            $provisioner,
            $projectDir,
        ));
        $c->setAlias(\App\Tenant\TenantService::class, 'tenant.service');

        // 2FA
        $c->set(\RLSQ\Security\TwoFactor\TwoFactorManager::class,
            new \RLSQ\Security\TwoFactor\TwoFactorManager($c->get('database.connection')));
        $c->set(\RLSQ\Security\TwoFactor\EmailCodeSender::class,
            new \RLSQ\Security\TwoFactor\EmailCodeSender($c->get('mailer')));

        // Plugin system
        $registry = new \RLSQ\Plugin\PluginRegistry();
        $registry->register(new \App\Plugin\FormationPlugin\FormationPlugin());
        $registry->register(new \App\Plugin\ActivityPlugin\ActivityPlugin());
        $registry->register(new \App\Plugin\CalendarPlugin\CalendarPlugin());
        $registry->register(new \App\Plugin\RoomBookingPlugin\RoomBookingPlugin());
        $registry->register(new \App\Plugin\PaymentPlugin\PaymentPlugin());
        $c->set('plugin.registry', $registry);
        $c->setAlias(\RLSQ\Plugin\PluginRegistry::class, 'plugin.registry');

        $pluginManager = new \RLSQ\Plugin\PluginManager($registry);
        $c->set('plugin.manager', $pluginManager);
        $c->setAlias(\RLSQ\Plugin\PluginManager::class, 'plugin.manager');
    }

    private function registerEventSubscribers(): void
    {
        $dispatcher = $this->container->get('event_dispatcher');
        $profiler = $this->container->get('profiler');

        // Event collector tracing
        if ($dispatcher instanceof \RLSQ\Profiler\TraceableEventDispatcher) {
            $eventCollector = $this->container->get('collector.event');
            $dispatcher->setEventCollector($eventCollector);
        }

        // Profiler collectors
        $profiler->addCollector($this->container->get('collector.request'));
        $profiler->addCollector($this->container->get('collector.route'));
        $profiler->addCollector($this->container->get('collector.performance'));
        $profiler->addCollector($this->container->get('collector.event'));
        $profiler->addCollector($this->container->get('collector.mailer'));

        // Tenant listener (before router)
        $dispatcher->addSubscriber(new \App\Tenant\TenantListener(
            $this->container->get('tenant.resolver'),
            $this->container->get('tenant.context'),
        ));

        // Core listeners
        $dispatcher->addSubscriber(new \RLSQ\HttpKernel\EventListener\RouterListener(
            $this->container->get('url_matcher'),
        ));
        $dispatcher->addSubscriber(new \RLSQ\Security\SecurityListener(
            $this->container->get('token_storage'),
        ));
        $dispatcher->addSubscriber(new \RLSQ\HttpKernel\EventListener\ExceptionListener(
            $this->debug,
        ));

        // JWT authenticator listener
        $dispatcher->addSubscriber(new \RLSQ\Security\Jwt\JwtListener(
            $this->container->get('jwt_manager'),
            $this->container->get('token_storage'),
        ));

        // Profiler toolbar (dev only)
        if ($this->debug) {
            $dispatcher->addSubscriber(new \RLSQ\Profiler\ProfilerListener(
                $profiler,
                new \RLSQ\Profiler\WebDebugToolbar(),
            ));
        }
    }

    private function resolvePath(string $path): string
    {
        if ($path === ':memory:') {
            return $path;
        }

        // Si déjà absolu, garder tel quel
        if (str_starts_with($path, '/') || (strlen($path) > 2 && $path[1] === ':')) {
            return $path;
        }

        return $this->projectDir . '/' . $path;
    }

    private function getHttpKernel(): HttpKernel
    {
        return $this->container->get('http_kernel');
    }
}
