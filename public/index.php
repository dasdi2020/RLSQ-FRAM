<?php

declare(strict_types=1);

use RLSQ\Controller\Attribute\AttributeRouteLoader;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\WelcomePage;
use RLSQ\Kernel;
use RLSQ\Routing\Route;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// --- Kernel Boot ---
$kernel = new Kernel(dirname(__DIR__));
$kernel->boot();

$container = $kernel->getContainer();
$routes = $container->get('route_collection');

// --- Exécuter les migrations au premier démarrage ---
$migrationManager = new \RLSQ\Database\Migration\MigrationManager($container->get('database.connection'));
$migrationManager->addMigrations([
    new \App\Migration\M001_CreateUsersTable(),
    new \App\Migration\M002_SeedSuperAdmin(),
    new \App\Migration\M003_CreateTenantsTable(),
    new \App\Migration\M004_CreateVersionsTables(),
    new \App\Migration\M005_CreateProjectsTable(),
]);
$migrationManager->migrate();

// --- Routes applicatives (contrôleurs) ---
$loader = new AttributeRouteLoader();
$routes->addCollection($loader->loadAll([
    \App\Controller\AuthController::class,
    \App\Controller\AdminTenantController::class,
    \App\Controller\ProjectController::class,
    \App\Controller\SchemaController::class,
    \App\Controller\DynamicDataController::class,
    \App\Controller\PluginController::class,
    \App\Controller\TenantAuthController::class,
    \App\Controller\DashboardController::class,
    \App\Controller\FormBuilderController::class,
    \App\Plugin\PaymentPlugin\Controller\PaymentController::class,
    \App\Controller\PageBuilderController::class,
    \App\Controller\VersioningController::class,
    \App\Controller\EmbedController::class,
]));

// --- Page d'accueil ---
$routes->add('home', new Route('/', [
    '_controller' => function () use ($routes): Response {
        $routeInfos = [];
        foreach ($routes->all() as $name => $route) {
            $ctrl = $route->getController();
            $routeInfos[$name] = [
                'path' => $route->getPath(),
                'methods' => $route->getMethods() ?: ['ANY'],
                'controller' => is_string($ctrl) ? $ctrl : (is_callable($ctrl) ? 'Closure' : 'N/A'),
            ];
        }

        return new Response(WelcomePage::render($routeInfos), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    },
]));

// --- API Status ---
$routes->add('api_status', new Route('/api/status', [
    '_controller' => function () use ($container): JsonResponse {
        return new JsonResponse([
            'status' => 'ok',
            'framework' => 'RLSQ-FRAM',
            'version' => '0.1.0',
            'php' => PHP_VERSION,
            'env' => $container->getParameter('kernel.environment'),
        ]);
    },
], ['GET']));

// --- OpenAPI / Swagger ---
$routes->add('openapi_spec', new Route('/api/openapi.json', [
    '_controller' => function () use ($routes): JsonResponse {
        $gen = new \RLSQ\OpenApi\OpenApiGenerator('RLSQ-FRAM API', '0.1.0', 'API de la plateforme RLSQ-FRAM');
        $spec = $gen->generateFromControllers([\App\Controller\AuthController::class]);
        // Fusionner avec les routes manuelles
        $routeSpec = $gen->generateFromRoutes($routes);
        foreach ($routeSpec['paths'] ?? [] as $path => $methods) {
            if (!isset($spec['paths'][$path])) {
                $spec['paths'][$path] = $methods;
            }
        }

        return new JsonResponse($spec);
    },
], ['GET']));

$routes->add('swagger_ui', new Route('/api/docs', [
    '_controller' => fn () => new Response(
        \RLSQ\OpenApi\SwaggerUi::render('/api/openapi.json'),
        200,
        ['Content-Type' => 'text/html; charset=UTF-8'],
    ),
], ['GET']));

// --- GraphQL ---
$gqlSchema = new \RLSQ\GraphQL\Schema();
$gqlSchema->addType(
    (new \RLSQ\GraphQL\TypeDefinition('Status'))
        ->addField('status', 'String!')
        ->addField('framework', 'String!')
        ->addField('php', 'String!')
);
$gqlSchema->addQuery('status', new \RLSQ\GraphQL\FieldDefinition(
    'Status',
    fn () => ['status' => 'ok', 'framework' => 'RLSQ-FRAM', 'php' => PHP_VERSION],
));
$gqlExecutor = new \RLSQ\GraphQL\Executor($gqlSchema);

$routes->add('graphql', new Route('/graphql', [
    '_controller' => function (Request $request) use ($gqlExecutor): JsonResponse {
        $body = json_decode($request->getContent(), true) ?? [];
        return new JsonResponse($gqlExecutor->execute($body['query'] ?? '', $body['variables'] ?? []));
    },
], ['POST']));

$routes->add('graphiql', new Route('/graphiql', [
    '_controller' => fn () => new Response(
        \RLSQ\GraphQL\GraphiQL::render('/graphql'),
        200,
        ['Content-Type' => 'text/html; charset=UTF-8'],
    ),
], ['GET']));

// --- Handle ---
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
