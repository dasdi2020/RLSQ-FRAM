<?php

declare(strict_types=1);

namespace Tests\HttpKernel;

use PHPUnit\Framework\TestCase;
use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\Event\RequestEvent;
use RLSQ\HttpKernel\Event\ResponseEvent;
use RLSQ\HttpKernel\Event\ExceptionEvent;
use RLSQ\HttpKernel\Event\TerminateEvent;
use RLSQ\HttpKernel\Event\ViewEvent;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\HttpKernel\KernelEvents;
use RLSQ\Routing\Matcher\UrlMatcher;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

class HttpKernelTest extends TestCase
{
    private function createKernel(RouteCollection $routes, bool $debug = false): HttpKernel
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new ExceptionListener($debug));

        return new HttpKernel(
            $dispatcher,
            new ControllerResolver(),
            new ArgumentResolver(),
        );
    }

    private function routes(): RouteCollection
    {
        $routes = new RouteCollection();

        $routes->add('home', new Route('/', [
            '_controller' => function (): Response {
                return new Response('Hello RLSQ-FRAM!');
            },
        ]));

        $routes->add('greet', new Route('/greet/{name}', [
            '_controller' => function (string $name): Response {
                return new Response('Hello ' . $name);
            },
        ]));

        $routes->add('typed', new Route('/article/{id}', [
            '_controller' => function (int $id, Request $request): Response {
                return new Response('Article #' . $id . ' via ' . $request->getMethod());
            },
        ], ['GET'], ['id' => '\d+']));

        $routes->add('post_only', new Route('/submit', [
            '_controller' => function (Request $request): Response {
                return new Response('Submitted', 201);
            },
        ], ['POST']));

        return $routes;
    }

    // --- Tests du cycle complet ---

    public function testHandleSimpleRoute(): void
    {
        $kernel = $this->createKernel($this->routes());
        $response = $kernel->handle(Request::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello RLSQ-FRAM!', $response->getContent());
    }

    public function testHandleWithRouteParameter(): void
    {
        $kernel = $this->createKernel($this->routes());
        $response = $kernel->handle(Request::create('/greet/Alice'));

        $this->assertSame('Hello Alice', $response->getContent());
    }

    public function testHandleWithTypedParameterAndRequest(): void
    {
        $kernel = $this->createKernel($this->routes());
        $response = $kernel->handle(Request::create('/article/42'));

        $this->assertSame('Article #42 via GET', $response->getContent());
    }

    public function testHandle404(): void
    {
        $kernel = $this->createKernel($this->routes());
        $response = $kernel->handle(Request::create('/nonexistent'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testHandle405(): void
    {
        $kernel = $this->createKernel($this->routes());
        $response = $kernel->handle(Request::create('/submit', 'GET'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST', $response->headers->get('allow'));
    }

    public function testHandleRequirementRejects(): void
    {
        $kernel = $this->createKernel($this->routes());
        $response = $kernel->handle(Request::create('/article/abc'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDebugModeShowsExceptionDetails(): void
    {
        $kernel = $this->createKernel($this->routes(), debug: true);
        $response = $kernel->handle(Request::create('/nonexistent'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Aucune route', $response->getContent());
    }

    // --- Tests des événements ---

    public function testRequestEventCanShortCircuit(): void
    {
        $routes = new RouteCollection();
        $routes->add('home', new Route('/', ['_controller' => function (): Response {
            return new Response('should not reach');
        }]));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));

        // Listener haute priorité qui court-circuite
        $dispatcher->addListener(KernelEvents::REQUEST, function (RequestEvent $event) {
            $event->setResponse(new Response('intercepted', 200));
        }, 100);

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());
        $response = $kernel->handle(Request::create('/'));

        $this->assertSame('intercepted', $response->getContent());
    }

    public function testResponseEventModifiesResponse(): void
    {
        $routes = new RouteCollection();
        $routes->add('home', new Route('/', ['_controller' => function (): Response {
            return new Response('original');
        }]));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addListener(KernelEvents::RESPONSE, function (ResponseEvent $event) {
            $event->getResponse()->headers->set('X-Framework', 'RLSQ-FRAM');
        });

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());
        $response = $kernel->handle(Request::create('/'));

        $this->assertSame('RLSQ-FRAM', $response->headers->get('X-Framework'));
    }

    public function testViewEventConvertsNonResponse(): void
    {
        $routes = new RouteCollection();
        $routes->add('data', new Route('/data', [
            '_controller' => function (): array {
                return ['key' => 'value'];
            },
        ]));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));

        // Listener kernel.view qui convertit un array en JsonResponse
        $dispatcher->addListener(KernelEvents::VIEW, function (ViewEvent $event) {
            $result = $event->getControllerResult();
            if (is_array($result)) {
                $event->setResponse(new \RLSQ\HttpFoundation\JsonResponse($result));
            }
        });

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());
        $response = $kernel->handle(Request::create('/data'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"key":"value"}', $response->getContent());
    }

    public function testExceptionWhenControllerReturnsNonResponseWithoutViewListener(): void
    {
        $routes = new RouteCollection();
        $routes->add('bad', new Route('/bad', [
            '_controller' => function (): string {
                return 'not a Response';
            },
        ]));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new ExceptionListener(true));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());
        $response = $kernel->handle(Request::create('/bad'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('retourner une Response', $response->getContent());
    }

    public function testTerminate(): void
    {
        $terminated = false;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::TERMINATE, function () use (&$terminated) {
            $terminated = true;
        });

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());

        $request = Request::create('/');
        $response = new Response('ok');
        $kernel->terminate($request, $response);

        $this->assertTrue($terminated);
    }
}
