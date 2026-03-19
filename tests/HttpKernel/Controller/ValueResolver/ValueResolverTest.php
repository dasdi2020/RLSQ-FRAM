<?php

declare(strict_types=1);

namespace Tests\HttpKernel\Controller\ValueResolver;

use PHPUnit\Framework\TestCase;
use RLSQ\Controller\Attribute\AttributeRouteLoader;
use RLSQ\Controller\Attribute\Route;
use RLSQ\Database\Connection;
use RLSQ\Database\ORM\EntityManager;
use RLSQ\Database\ORM\Mapping\Column;
use RLSQ\Database\ORM\Mapping\Entity;
use RLSQ\Database\ORM\Mapping\GeneratedValue;
use RLSQ\Database\ORM\Mapping\Id;
use RLSQ\DependencyInjection\ContainerBuilder;
use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\Controller\ValueResolver\EntityValueResolver;
use RLSQ\HttpKernel\Controller\ValueResolver\MapEntity;
use RLSQ\HttpKernel\Controller\ValueResolver\ServiceValueResolver;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\Routing\Matcher\UrlMatcher;

class ValueResolverTest extends TestCase
{
    private EntityManager $em;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        // DB in-memory
        $conn = new Connection('sqlite::memory:');
        $this->em = new EntityManager($conn);
        $this->em->createSchema([VRArticle::class]);

        // Insérer des données
        $a = new VRArticle();
        $a->title = 'Hello World';
        $this->em->persist($a);
        $this->em->flush();

        // Container avec services
        $this->container = new ContainerBuilder();
        $this->container->set('dummy_service', new DummyService());
        $this->container->set(DummyService::class, new DummyService());
    }

    private function createKernel(string $controllerClass): HttpKernel
    {
        $routes = (new AttributeRouteLoader())->load($controllerClass);

        $argumentResolver = new ArgumentResolver([
            new EntityValueResolver($this->em),
            new ServiceValueResolver($this->container),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new ExceptionListener(debug: true));

        return new HttpKernel(
            $dispatcher,
            new ControllerResolver($this->container),
            $argumentResolver,
        );
    }

    // --- Injection de Request ---

    public function testInjectsRequest(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/request-info'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/request-info', $response->getContent());
    }

    // --- Paramètres scalaires de route ---

    public function testInjectsScalarParameters(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/greet/Alice'));

        $this->assertSame('Hello Alice', $response->getContent());
    }

    public function testCastsIntParameter(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/number/42'));

        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(42, $decoded['value']);
        $this->assertSame('integer', $decoded['type']);
    }

    // --- Injection d'entité ---

    public function testInjectsEntity(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/article/1'));

        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame('Hello World', $decoded['title']);
    }

    public function testEntity404WhenNotFound(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/article/999'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testEntityNullableReturnsNull(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/article-nullable/999'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('null', $response->getContent());
    }

    // --- MapEntity ---

    public function testMapEntityCustomId(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/by-article-id/1'));

        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame('Hello World', $decoded['title']);
    }

    // --- Injection de service ---

    public function testInjectsService(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/with-service'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('service works', $response->getContent());
    }

    // --- Mix de tout ---

    public function testMixedParameters(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/mixed/1'));

        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame('Hello World', $decoded['article']);
        $this->assertSame('service works', $decoded['service']);
        $this->assertSame('/mixed/1', $decoded['path']);
    }

    // --- Valeur par défaut ---

    public function testDefaultValue(): void
    {
        $kernel = $this->createKernel(VRController::class);
        $response = $kernel->handle(Request::create('/with-default'));

        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(1, $decoded['page']);
    }
}

// === Entité de test ===

#[Entity(table: 'vr_articles')]
class VRArticle
{
    #[Id, Column(type: 'integer'), GeneratedValue]
    public int $id;

    #[Column(type: 'string')]
    public string $title;
}

// === Service de test ===

class DummyService
{
    public function work(): string
    {
        return 'service works';
    }
}

// === Contrôleur de test ===

class VRController
{
    #[Route('/request-info', name: 'vr_request')]
    public function requestInfo(Request $request): Response
    {
        return new Response('Path: ' . $request->getPathInfo());
    }

    #[Route('/greet/{name}', name: 'vr_greet')]
    public function greet(string $name): Response
    {
        return new Response('Hello ' . $name);
    }

    #[Route('/number/{value}', name: 'vr_number', requirements: ['value' => '\d+'])]
    public function number(int $value): JsonResponse
    {
        return new JsonResponse(['value' => $value, 'type' => gettype($value)]);
    }

    #[Route('/article/{id}', name: 'vr_article', requirements: ['id' => '\d+'])]
    public function showArticle(VRArticle $article): JsonResponse
    {
        return new JsonResponse(['id' => $article->id, 'title' => $article->title]);
    }

    #[Route('/article-nullable/{id}', name: 'vr_article_null', requirements: ['id' => '\d+'])]
    public function showArticleNullable(?VRArticle $article): JsonResponse
    {
        return new JsonResponse(['article' => $article?->title ?? 'null']);
    }

    #[Route('/by-article-id/{article_id}', name: 'vr_map_entity', requirements: ['article_id' => '\d+'])]
    public function mapEntity(#[MapEntity(id: 'article_id')] VRArticle $article): JsonResponse
    {
        return new JsonResponse(['id' => $article->id, 'title' => $article->title]);
    }

    #[Route('/with-service', name: 'vr_service')]
    public function withService(DummyService $service): Response
    {
        return new Response($service->work());
    }

    #[Route('/mixed/{id}', name: 'vr_mixed', requirements: ['id' => '\d+'])]
    public function mixed(VRArticle $article, DummyService $service, Request $request): JsonResponse
    {
        return new JsonResponse([
            'article' => $article->title,
            'service' => $service->work(),
            'path' => $request->getPathInfo(),
        ]);
    }

    #[Route('/with-default', name: 'vr_default')]
    public function withDefault(int $page = 1): JsonResponse
    {
        return new JsonResponse(['page' => $page]);
    }
}
