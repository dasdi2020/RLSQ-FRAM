<?php

declare(strict_types=1);

namespace RLSQ\OpenApi;

use RLSQ\Controller\Attribute\Route;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\OpenApi\Attribute\ApiSchema;
use RLSQ\Routing\RouteCollection;
use RLSQ\Security\Attribute\IsGranted;
use RLSQ\Security\Attribute\RequireAuth;

/**
 * Génère une spécification OpenAPI 3.0 depuis les routes et attributs.
 */
class OpenApiGenerator
{
    public function __construct(
        private readonly string $title = 'RLSQ-FRAM API',
        private readonly string $version = '0.1.0',
        private readonly string $description = '',
    ) {}

    /**
     * Génère la spec depuis des classes de contrôleurs.
     *
     * @param string[] $controllerClasses
     */
    public function generateFromControllers(array $controllerClasses): array
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
                'description' => $this->description,
            ],
            'paths' => [],
            'components' => ['schemas' => [], 'securitySchemes' => []],
        ];

        foreach ($controllerClasses as $class) {
            $this->processController($class, $spec);
        }

        // Ajouter un scheme bearer si des routes requièrent l'auth
        if (!empty($spec['components']['securitySchemes'])) {
            // Déjà ajouté dans processController
        }

        // Nettoyer les sections vides
        if (empty($spec['components']['schemas'])) {
            unset($spec['components']['schemas']);
        }
        if (empty($spec['components']['securitySchemes'])) {
            unset($spec['components']['securitySchemes']);
        }
        if (empty($spec['components'])) {
            unset($spec['components']);
        }

        return $spec;
    }

    /**
     * Génère la spec depuis une RouteCollection (routes non-controleur).
     */
    public function generateFromRoutes(RouteCollection $routes): array
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
                'description' => $this->description,
            ],
            'paths' => [],
        ];

        foreach ($routes->all() as $name => $route) {
            $path = $this->convertPathToOpenApi($route->getPath());
            $methods = $route->getMethods() ?: ['GET'];

            foreach ($methods as $method) {
                $operation = [
                    'operationId' => $name . '_' . strtolower($method),
                    'summary' => $name,
                    'responses' => [
                        '200' => ['description' => 'Succès'],
                    ],
                ];

                // Extraire les paramètres du path
                $params = $this->extractPathParameters($route->getPath(), $route->getRequirements());
                if (!empty($params)) {
                    $operation['parameters'] = $params;
                }

                $spec['paths'][$path][strtolower($method)] = $operation;
            }
        }

        return $spec;
    }

    private function processController(string $class, array &$spec): void
    {
        $ref = new \ReflectionClass($class);

        // Préfixe de route sur la classe
        $classPrefix = '';
        $classRouteAttrs = $ref->getAttributes(Route::class);
        if (!empty($classRouteAttrs)) {
            $classPrefix = $classRouteAttrs[0]->newInstance()->path;
        }

        // Sécurité au niveau classe
        $classSecurity = $this->extractSecurity($ref->getAttributes(IsGranted::class), $ref->getAttributes(RequireAuth::class));

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttrs = $method->getAttributes(Route::class);
            if (empty($routeAttrs)) {
                continue;
            }

            foreach ($routeAttrs as $routeAttr) {
                $route = $routeAttr->newInstance();
                $path = rtrim($classPrefix, '/') . '/' . ltrim($route->path, '/');
                if ($path !== '/') {
                    $path = rtrim($path, '/');
                }

                $openApiPath = $this->convertPathToOpenApi($path);
                $httpMethods = $route->methods ?: ['GET'];

                // Métadonnées OpenAPI
                $apiRouteAttrs = $method->getAttributes(ApiRoute::class);
                $apiRoute = !empty($apiRouteAttrs) ? $apiRouteAttrs[0]->newInstance() : null;

                // Sécurité au niveau méthode
                $methodSecurity = $this->extractSecurity(
                    $method->getAttributes(IsGranted::class),
                    $method->getAttributes(RequireAuth::class),
                );
                $security = array_merge($classSecurity, $methodSecurity);

                foreach ($httpMethods as $httpMethod) {
                    $operation = $this->buildOperation(
                        $route,
                        $apiRoute,
                        $method,
                        $security,
                    );

                    $spec['paths'][$openApiPath][strtolower($httpMethod)] = $operation;
                }

                // Ajouter le security scheme si nécessaire
                if (!empty($security)) {
                    $spec['components']['securitySchemes']['bearerAuth'] = [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ];
                }
            }
        }
    }

    private function buildOperation(Route $route, ?ApiRoute $apiRoute, \ReflectionMethod $method, array $security): array
    {
        $name = $route->name ?? $method->getName();
        $operation = [
            'operationId' => $name,
            'summary' => $apiRoute?->summary ?? $name,
        ];

        if ($apiRoute?->description !== null) {
            $operation['description'] = $apiRoute->description;
        }

        if (!empty($apiRoute?->tags)) {
            $operation['tags'] = $apiRoute->tags;
        }

        if ($apiRoute?->deprecated) {
            $operation['deprecated'] = true;
        }

        // Parameters (path + query)
        $params = $this->extractPathParameters($route->path, $route->requirements);
        if (!empty($apiRoute?->parameters)) {
            foreach ($apiRoute->parameters as $p) {
                $params[] = $p;
            }
        }
        if (!empty($params)) {
            $operation['parameters'] = $params;
        }

        // Request body
        if ($apiRoute?->requestBody !== null) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => $apiRoute->requestBody,
                    ],
                ],
            ];
        }

        // Responses
        if (!empty($apiRoute?->responses)) {
            $operation['responses'] = [];
            foreach ($apiRoute->responses as $code => $desc) {
                $operation['responses'][(string) $code] = is_string($desc)
                    ? ['description' => $desc]
                    : $desc;
            }
        } else {
            $operation['responses'] = ['200' => ['description' => 'Succès']];
        }

        // Security
        if (!empty($security)) {
            $operation['security'] = [['bearerAuth' => []]];
            foreach ($security as $role) {
                $operation['responses']['401'] ??= ['description' => 'Non authentifié'];
                $operation['responses']['403'] ??= ['description' => 'Accès refusé — ' . $role . ' requis'];
            }
        }

        return $operation;
    }

    /**
     * Génère un schéma depuis les attributs d'une classe.
     */
    public function generateSchema(string $class): array
    {
        $ref = new \ReflectionClass($class);
        $schemaAttrs = $ref->getAttributes(ApiSchema::class);
        $schema = !empty($schemaAttrs) ? $schemaAttrs[0]->newInstance() : null;

        $properties = [];
        $required = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $type = $prop->getType();
            $propSchema = ['type' => 'string'];

            if ($type instanceof \ReflectionNamedType) {
                $propSchema['type'] = match ($type->getName()) {
                    'int' => 'integer',
                    'float' => 'number',
                    'bool' => 'boolean',
                    'array' => 'array',
                    default => 'string',
                };

                if (!$type->allowsNull() && !$prop->hasDefaultValue()) {
                    $required[] = $prop->getName();
                }
            }

            $properties[$prop->getName()] = $propSchema;
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $result['required'] = $required;
        }

        if ($schema?->description !== null) {
            $result['description'] = $schema->description;
        }

        return $result;
    }

    private function convertPathToOpenApi(string $path): string
    {
        return preg_replace('/\{(\w+)\}/', '{$1}', $path);
    }

    private function extractPathParameters(string $path, array $requirements = []): array
    {
        $params = [];

        if (preg_match_all('/\{(\w+)\}/', $path, $matches)) {
            foreach ($matches[1] as $name) {
                $param = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ];

                if (isset($requirements[$name]) && $requirements[$name] === '\d+') {
                    $param['schema'] = ['type' => 'integer'];
                }

                $params[] = $param;
            }
        }

        return $params;
    }

    private function extractSecurity(array $isGrantedAttrs, array $requireAuthAttrs): array
    {
        $roles = [];

        foreach ($isGrantedAttrs as $attr) {
            $roles[] = $attr->newInstance()->attribute;
        }

        if (!empty($requireAuthAttrs)) {
            $roles[] = 'authenticated';
        }

        return $roles;
    }
}
