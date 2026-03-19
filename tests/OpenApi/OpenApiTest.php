<?php

declare(strict_types=1);

namespace Tests\OpenApi;

use PHPUnit\Framework\TestCase;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\Response;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\OpenApi\Attribute\ApiSchema;
use RLSQ\OpenApi\OpenApiGenerator;
use RLSQ\OpenApi\SwaggerUi;
use RLSQ\Routing\Route as RouteDef;
use RLSQ\Routing\RouteCollection;
use RLSQ\Security\Attribute\IsGranted;
use RLSQ\Security\Attribute\RequireAuth;

class OpenApiTest extends TestCase
{
    public function testGenerateFromControllers(): void
    {
        $gen = new OpenApiGenerator('Test API', '1.0', 'API de test');
        $spec = $gen->generateFromControllers([DocApiController::class]);

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('Test API', $spec['info']['title']);
        $this->assertArrayHasKey('/api/articles', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/articles']);
    }

    public function testApiRouteMetadata(): void
    {
        $gen = new OpenApiGenerator();
        $spec = $gen->generateFromControllers([DocApiController::class]);

        $listOp = $spec['paths']['/api/articles']['get'];

        $this->assertSame('Liste des articles', $listOp['summary']);
        $this->assertContains('Article', $listOp['tags']);
        $this->assertArrayHasKey('200', $listOp['responses']);
    }

    public function testPathParameters(): void
    {
        $gen = new OpenApiGenerator();
        $spec = $gen->generateFromControllers([DocApiController::class]);

        $showOp = $spec['paths']['/api/articles/{id}']['get'];

        $this->assertNotEmpty($showOp['parameters']);
        $this->assertSame('id', $showOp['parameters'][0]['name']);
        $this->assertSame('integer', $showOp['parameters'][0]['schema']['type']);
    }

    public function testSecurityAnnotations(): void
    {
        $gen = new OpenApiGenerator();
        $spec = $gen->generateFromControllers([DocApiController::class]);

        $createOp = $spec['paths']['/api/articles']['post'];

        $this->assertArrayHasKey('security', $createOp);
        $this->assertArrayHasKey('401', $createOp['responses']);
        $this->assertArrayHasKey('403', $createOp['responses']);
    }

    public function testGenerateFromRouteCollection(): void
    {
        $routes = new RouteCollection();
        $routes->add('home', new RouteDef('/', ['_controller' => 'Home::index']));
        $routes->add('user', new RouteDef('/user/{id}', [], ['GET'], ['id' => '\d+']));

        $gen = new OpenApiGenerator('Simple API');
        $spec = $gen->generateFromRoutes($routes);

        $this->assertArrayHasKey('/', $spec['paths']);
        $this->assertArrayHasKey('/user/{id}', $spec['paths']);
    }

    public function testGenerateSchema(): void
    {
        $gen = new OpenApiGenerator();
        $schema = $gen->generateSchema(ArticleDTO::class);

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertArrayHasKey('views', $schema['properties']);
        $this->assertSame('string', $schema['properties']['title']['type']);
        $this->assertSame('integer', $schema['properties']['views']['type']);
        $this->assertContains('title', $schema['required']);
    }

    public function testSwaggerUiRender(): void
    {
        $html = SwaggerUi::render('/api/spec.json', 'My API');

        $this->assertStringContainsString('swagger-ui', $html);
        $this->assertStringContainsString('/api/spec.json', $html);
        $this->assertStringContainsString('My API', $html);
    }
}

// --- Fixtures ---

#[Route('/api')]
class DocApiController
{
    #[Route('/articles', name: 'article_list', methods: ['GET'])]
    #[ApiRoute(summary: 'Liste des articles', tags: ['Article'], responses: [200 => 'Liste JSON'])]
    public function list(): Response { return new Response(); }

    #[Route('/articles/{id}', name: 'article_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[ApiRoute(summary: 'Détail article', tags: ['Article'])]
    public function show(int $id): Response { return new Response(); }

    #[Route('/articles', name: 'article_create', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[ApiRoute(
        summary: 'Créer un article',
        tags: ['Article'],
        requestBody: ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]],
        responses: [201 => 'Créé', 422 => 'Validation échouée'],
    )]
    public function create(): Response { return new Response(); }
}

#[ApiSchema(description: 'Un article du blog')]
class ArticleDTO
{
    public string $title;
    public string $content;
    public int $views = 0;
    public bool $published = false;
}
