<?php

declare(strict_types=1);

use RLSQ\DependencyInjection\ContainerBuilder;
use RLSQ\DependencyInjection\Reference;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\HttpKernel\WelcomePage;
use RLSQ\Profiler\Collector\EventCollector;
use RLSQ\Profiler\Collector\PerformanceCollector;
use RLSQ\Profiler\Collector\RequestCollector;
use RLSQ\Profiler\Collector\RouteCollector;
use RLSQ\Profiler\Profiler;
use RLSQ\Profiler\ProfilerListener;
use RLSQ\Profiler\TraceableEventDispatcher;
use RLSQ\Profiler\WebDebugToolbar;
use RLSQ\Routing\Matcher\UrlMatcher;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// --- Profiler (démarré le plus tôt possible) ---
$profiler = new Profiler();

// --- Routes ---
$routes = new RouteCollection();

$routes->add('home', new Route('/', [
    '_controller' => function () use ($routes): Response {
        // Collecter les infos des routes pour la page d'accueil
        $routeInfos = [];
        foreach ($routes->all() as $name => $route) {
            $controller = $route->getController();
            $routeInfos[$name] = [
                'path' => $route->getPath(),
                'methods' => $route->getMethods() ?: ['ANY'],
                'controller' => is_string($controller) ? $controller : (is_callable($controller) ? 'Closure' : 'N/A'),
            ];
        }

        return new Response(
            WelcomePage::render($routeInfos),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    },
]));

$routes->add('api_status', new Route('/api/status', [
    '_controller' => function (): JsonResponse {
        return new JsonResponse([
            'status' => 'ok',
            'framework' => 'RLSQ-FRAM',
            'version' => '0.1.0',
            'php' => PHP_VERSION,
        ]);
    },
], ['GET']));

// --- Event Dispatcher (traceable pour le profiler) ---
$dispatcher = new TraceableEventDispatcher();

// --- Profiler collectors ---
$eventCollector = new EventCollector($dispatcher);
$dispatcher->setEventCollector($eventCollector);

$profiler->addCollector(new RequestCollector());
$profiler->addCollector(new RouteCollector());
$profiler->addCollector(new PerformanceCollector($profiler));
$profiler->addCollector($eventCollector);

// --- Listeners ---
$dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
$dispatcher->addSubscriber(new ExceptionListener(debug: true));
$dispatcher->addSubscriber(new ProfilerListener($profiler, new WebDebugToolbar(), enabled: true));

// --- Kernel ---
$kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());

// --- Handle ---
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
