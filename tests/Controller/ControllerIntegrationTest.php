<?php

declare(strict_types=1);

namespace Tests\Controller;

use PHPUnit\Framework\TestCase;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\AttributeRouteLoader;
use RLSQ\Controller\Attribute\Route;
use RLSQ\DependencyInjection\Container;
use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\Exception\NotFoundHttpException;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\Routing\Generator\UrlGenerator;
use RLSQ\Routing\Matcher\UrlMatcher;

/**
 * Test d'intégration : attributs #[Route] + AbstractController + HttpKernel.
 */
class ControllerIntegrationTest extends TestCase
{
    private HttpKernel $kernel;

    protected function setUp(): void
    {
        $loader = new AttributeRouteLoader();
        $routes = $loader->load(IntegrationController::class);

        $container = new Container();
        $container->set('url_generator', new UrlGenerator($routes));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new ExceptionListener(debug: true));

        $this->kernel = new HttpKernel(
            $dispatcher,
            new ControllerResolver($container),
            new ArgumentResolver(),
        );
    }

    public function testHomePage(): void
    {
        $response = $this->kernel->handle(Request::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Welcome', $response->getContent());
    }

    public function testJsonEndpoint(): void
    {
        $response = $this->kernel->handle(Request::create('/api/info'));

        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame('RLSQ-FRAM', $decoded['framework']);
    }

    public function testRouteParameter(): void
    {
        $response = $this->kernel->handle(Request::create('/user/42'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('42', $response->getContent());
    }

    public function testNotFoundThrown(): void
    {
        $response = $this->kernel->handle(Request::create('/missing/99'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Utilisateur 99 introuvable', $response->getContent());
    }

    public function test404ForUnknownRoute(): void
    {
        $response = $this->kernel->handle(Request::create('/totally/unknown'));

        $this->assertSame(404, $response->getStatusCode());
    }
}

// --- Fixture contrôleur ---

class IntegrationController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return new Response('Welcome');
    }

    #[Route('/api/info', name: 'api_info', methods: ['GET'])]
    public function info(): JsonResponse
    {
        return $this->json(['framework' => 'RLSQ-FRAM', 'version' => '0.1']);
    }

    #[Route('/user/{id}', name: 'user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return new Response('User #' . $id);
    }

    #[Route('/missing/{id}', name: 'user_missing', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function missing(int $id): Response
    {
        throw $this->createNotFoundException('Utilisateur ' . $id . ' introuvable');
    }
}
