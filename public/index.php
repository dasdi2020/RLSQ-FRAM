<?php

declare(strict_types=1);

use RLSQ\DependencyInjection\ContainerBuilder;
use RLSQ\DependencyInjection\Reference;
use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\EventDispatcher\EventDispatcherInterface;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ArgumentResolverInterface;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\Controller\ControllerResolverInterface;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\HttpKernel\HttpKernelInterface;
use RLSQ\Routing\Matcher\UrlMatcher;
use RLSQ\Routing\Matcher\UrlMatcherInterface;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// --- Routes ---
$routes = new RouteCollection();

$routes->add('home', new Route('/', [
    '_controller' => function (): Response {
        return new Response(
            '<h1>RLSQ-FRAM</h1><p>Le framework fonctionne avec le Service Container !</p>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    },
]));

$routes->add('api_status', new Route('/api/status', [
    '_controller' => function (): JsonResponse {
        return new JsonResponse(['status' => 'ok', 'framework' => 'RLSQ-FRAM']);
    },
], ['GET']));

// --- Container ---
$container = new ContainerBuilder();

$container->setParameter('kernel.debug', true);

// Routes
$container->set('routes', $routes);

// Matcher
$container->register('url_matcher', UrlMatcher::class)
    ->setArguments([new Reference('routes')]);

// Event Dispatcher
$container->register('event_dispatcher', EventDispatcher::class);

// Listeners
$container->register('router_listener', RouterListener::class)
    ->setArguments([new Reference('url_matcher')]);

$container->register('exception_listener', ExceptionListener::class)
    ->setArguments(['%kernel.debug%']);

// Controller + Argument Resolver
$container->register('controller_resolver', ControllerResolver::class);
$container->register('argument_resolver', ArgumentResolver::class);

// HttpKernel
$container->register('http_kernel', HttpKernel::class)
    ->setArguments([
        new Reference('event_dispatcher'),
        new Reference('controller_resolver'),
        new Reference('argument_resolver'),
    ]);

$container->compile();

// Enregistrer les subscribers manuellement (en attendant un CompilerPass dédié)
$dispatcher = $container->get('event_dispatcher');
$dispatcher->addSubscriber($container->get('router_listener'));
$dispatcher->addSubscriber($container->get('exception_listener'));

// --- Handle ---
$kernel = $container->get('http_kernel');
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
