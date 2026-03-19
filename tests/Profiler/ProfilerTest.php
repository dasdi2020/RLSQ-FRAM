<?php

declare(strict_types=1);

namespace Tests\Profiler;

use PHPUnit\Framework\TestCase;
use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\HttpKernel;
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

class ProfilerTest extends TestCase
{
    public function testProfilerCollectsData(): void
    {
        $profiler = new Profiler();
        $profiler->addCollector(new RequestCollector());
        $profiler->addCollector(new RouteCollector());
        $profiler->addCollector(new PerformanceCollector($profiler));

        $request = Request::create('/test', 'GET');
        $request->attributes->set('_route', 'test_route');
        $request->attributes->set('_controller', 'TestController::index');

        $response = new Response('ok', 200);

        $profiler->collect($request, $response);

        // Request collector
        $reqData = $profiler->getCollector('request')->getData();
        $this->assertSame('GET', $reqData['method']);
        $this->assertSame('/test', $reqData['path']);
        $this->assertSame(200, $reqData['status_code']);

        // Route collector
        $routeData = $profiler->getCollector('route')->getData();
        $this->assertSame('test_route', $routeData['route']);
        $this->assertSame('TestController::index', $routeData['controller']);

        // Performance collector
        $perfData = $profiler->getCollector('performance')->getData();
        $this->assertArrayHasKey('duration_ms', $perfData);
        $this->assertArrayHasKey('memory_peak_formatted', $perfData);
        $this->assertSame(PHP_VERSION, $perfData['php_version']);
    }

    public function testProfilerDuration(): void
    {
        $profiler = new Profiler();

        usleep(50000); // 50ms

        $duration = $profiler->getDuration();
        $this->assertGreaterThan(10, $duration); // Au moins 10ms
    }

    public function testProfilerMemory(): void
    {
        $profiler = new Profiler();

        $this->assertGreaterThan(0, $profiler->getMemoryUsage());
    }

    public function testEventCollector(): void
    {
        $dispatcher = new TraceableEventDispatcher();
        $eventCollector = new EventCollector($dispatcher);
        $dispatcher->setEventCollector($eventCollector);

        // Ajouter un listener pour qu'il y ait des listeners
        $dispatcher->addListener('test.event', function () {});
        $dispatcher->dispatch(new \RLSQ\EventDispatcher\Event(), 'test.event');

        $eventCollector->collect(Request::create('/'), new Response());

        $data = $eventCollector->getData();
        $this->assertGreaterThan(0, $data['dispatched_count']);
        $this->assertSame('test.event', $data['dispatched_events'][0]['name']);
    }

    public function testToolbarRendersHtml(): void
    {
        $profiler = new Profiler();
        $profiler->addCollector(new RequestCollector());
        $profiler->addCollector(new RouteCollector());
        $profiler->addCollector(new PerformanceCollector($profiler));

        $request = Request::create('/');
        $request->attributes->set('_route', 'home');
        $response = new Response('ok', 200);

        $profiler->collect($request, $response);

        $toolbar = new WebDebugToolbar();
        $html = $toolbar->render($profiler);

        $this->assertStringContainsString('rlsq-wdt', $html);
        $this->assertStringContainsString('200', $html);
        $this->assertStringContainsString('home', $html);
        $this->assertStringContainsString('PHP', $html);
        $this->assertStringContainsString('rlsq-profiler', $html);
        $this->assertStringContainsString('wdt-tab', $html);
        $this->assertStringContainsString('wdt-panel', $html);
    }

    public function testProfilerListenerInjectsToolbar(): void
    {
        $profiler = new Profiler();
        $profiler->addCollector(new RequestCollector());
        $profiler->addCollector(new RouteCollector());
        $profiler->addCollector(new PerformanceCollector($profiler));

        $routes = new RouteCollection();
        $routes->add('home', new Route('/', [
            '_controller' => function (): Response {
                return new Response(
                    '<html><body><h1>Hello</h1></body></html>',
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8'],
                );
            },
        ]));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new ExceptionListener());
        $dispatcher->addSubscriber(new ProfilerListener($profiler, new WebDebugToolbar()));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());

        $response = $kernel->handle(Request::create('/'));

        $content = $response->getContent();
        $this->assertStringContainsString('<h1>Hello</h1>', $content);
        $this->assertStringContainsString('rlsq-wdt', $content);
        $this->assertStringContainsString('</body>', $content);
    }

    public function testProfilerNotInjectedOnJson(): void
    {
        $profiler = new Profiler();
        $profiler->addCollector(new RequestCollector());

        $routes = new RouteCollection();
        $routes->add('api', new Route('/api', [
            '_controller' => function (): Response {
                return new \RLSQ\HttpFoundation\JsonResponse(['ok' => true]);
            },
        ]));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new ProfilerListener($profiler, new WebDebugToolbar()));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());

        $response = $kernel->handle(Request::create('/api'));

        $this->assertStringNotContainsString('rlsq-wdt', $response->getContent());
    }

    public function testProfilerDisabled(): void
    {
        $profiler = new Profiler();
        $profiler->addCollector(new RequestCollector());

        $routes = new RouteCollection();
        $routes->add('home', new Route('/', [
            '_controller' => function (): Response {
                return new Response('<html><body>test</body></html>', 200, ['Content-Type' => 'text/html']);
            },
        ]));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new ProfilerListener($profiler, new WebDebugToolbar(), enabled: false));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());

        $response = $kernel->handle(Request::create('/'));

        $this->assertStringNotContainsString('rlsq-wdt', $response->getContent());
    }
}
