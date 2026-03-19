<?php

declare(strict_types=1);

namespace Tests\Controller;

use PHPUnit\Framework\TestCase;
use RLSQ\Controller\AbstractController;
use RLSQ\DependencyInjection\Container;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\RedirectResponse;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Exception\AccessDeniedHttpException;
use RLSQ\HttpKernel\Exception\NotFoundHttpException;
use RLSQ\Routing\Generator\UrlGenerator;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

class AbstractControllerTest extends TestCase
{
    private function createController(): TestController
    {
        $routes = new RouteCollection();
        $routes->add('home', new Route('/'));
        $routes->add('article', new Route('/article/{id}'));

        $container = new Container();
        $container->set('url_generator', new UrlGenerator($routes));

        $controller = new TestController();
        $controller->setContainer($container);

        return $controller;
    }

    public function testJson(): void
    {
        $controller = $this->createController();
        $response = $controller->callJson(['status' => 'ok']);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"status":"ok"}', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testJsonWithStatus(): void
    {
        $controller = $this->createController();
        $response = $controller->callJson(['error' => 'fail'], 422);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testRedirect(): void
    {
        $controller = $this->createController();
        $response = $controller->callRedirect('/login');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('location'));
    }

    public function testRedirectToRoute(): void
    {
        $controller = $this->createController();
        $response = $controller->callRedirectToRoute('article', ['id' => 42]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/article/42', $response->getTargetUrl());
    }

    public function testGenerateUrl(): void
    {
        $controller = $this->createController();

        $this->assertSame('/', $controller->callGenerateUrl('home'));
        $this->assertSame('/article/5', $controller->callGenerateUrl('article', ['id' => 5]));
    }

    public function testGetParameter(): void
    {
        $container = new Container();
        $container->setParameter('app.debug', true);

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertTrue($controller->callGetParameter('app.debug'));
    }

    public function testCreateNotFoundException(): void
    {
        $controller = $this->createController();
        $exception = $controller->callCreateNotFoundException('Page introuvable');

        $this->assertInstanceOf(NotFoundHttpException::class, $exception);
        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('Page introuvable', $exception->getMessage());
    }

    public function testCreateAccessDeniedException(): void
    {
        $controller = $this->createController();
        $exception = $controller->callCreateAccessDeniedException();

        $this->assertInstanceOf(AccessDeniedHttpException::class, $exception);
        $this->assertSame(403, $exception->getStatusCode());
    }

    public function testGetService(): void
    {
        $container = new Container();
        $service = new \stdClass();
        $container->set('my_service', $service);

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertSame($service, $controller->callGet('my_service'));
        $this->assertTrue($controller->callHas('my_service'));
        $this->assertFalse($controller->callHas('nonexistent'));
    }
}

/**
 * Contrôleur de test exposant les méthodes protégées.
 */
class TestController extends AbstractController
{
    public function callJson(mixed $data, int $status = 200): JsonResponse
    {
        return $this->json($data, $status);
    }

    public function callRedirect(string $url): RedirectResponse
    {
        return $this->redirect($url);
    }

    public function callRedirectToRoute(string $route, array $params = []): RedirectResponse
    {
        return $this->redirectToRoute($route, $params);
    }

    public function callGenerateUrl(string $route, array $params = []): string
    {
        return $this->generateUrl($route, $params);
    }

    public function callGetParameter(string $name): mixed
    {
        return $this->getParameter($name);
    }

    public function callCreateNotFoundException(string $msg = 'Not Found'): NotFoundHttpException
    {
        return $this->createNotFoundException($msg);
    }

    public function callCreateAccessDeniedException(): AccessDeniedHttpException
    {
        return $this->createAccessDeniedException();
    }

    public function callGet(string $id): mixed
    {
        return $this->get($id);
    }

    public function callHas(string $id): bool
    {
        return $this->has($id);
    }
}
