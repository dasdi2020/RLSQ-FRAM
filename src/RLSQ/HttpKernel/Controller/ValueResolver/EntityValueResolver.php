<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller\ValueResolver;

use RLSQ\Database\ORM\EntityManager;
use RLSQ\Database\ORM\Mapping\Entity;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpKernel\Exception\NotFoundHttpException;

/**
 * Résout automatiquement une entité depuis un paramètre de route.
 *
 * Si le contrôleur type-hint une classe annotée #[Entity] et qu'un paramètre
 * de route {id} (ou {nomDuParam}) existe, l'entité est chargée automatiquement.
 *
 * Usage :
 *   #[Route('/article/{id}')]
 *   public function show(Article $article): Response { ... }
 *
 *   #[Route('/user/{user_id}')]
 *   public function profile(#[MapEntity(id: 'user_id')] User $user): Response { ... }
 */
class EntityValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly EntityManager $em,
    ) {}

    public function resolve(Request $request, \ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $className = $type->getName();

        if (!class_exists($className)) {
            return [];
        }

        // Vérifier que la classe est une entité (#[Entity])
        $ref = new \ReflectionClass($className);
        if (empty($ref->getAttributes(Entity::class))) {
            return [];
        }

        // Chercher le champ d'identification dans les attributs de la Request
        $id = $this->resolveId($request, $parameter, $className);

        if ($id === null) {
            return [];
        }

        $entity = $this->em->find($className, $id);

        if ($entity === null) {
            if ($parameter->allowsNull()) {
                return [null];
            }

            $shortName = $ref->getShortName();
            throw new NotFoundHttpException(sprintf('%s #%s introuvable.', $shortName, $id));
        }

        return [$entity];
    }

    private function resolveId(Request $request, \ReflectionParameter $parameter, string $className): int|string|null
    {
        // 1. Vérifier l'attribut #[MapEntity(id: 'field_name')]
        $mapEntityAttrs = $parameter->getAttributes(MapEntity::class);
        if (!empty($mapEntityAttrs)) {
            $mapEntity = $mapEntityAttrs[0]->newInstance();
            $field = $mapEntity->id;

            if ($request->attributes->has($field)) {
                return $request->attributes->get($field);
            }
        }

        // 2. Chercher par le nom du paramètre (ex: $article → attributes['article'] si c'est un id)
        //    Mais c'est ambigu, on préfère 'id'
        // 3. Chercher {id} dans les attributs de route
        if ($request->attributes->has('id')) {
            return $request->attributes->get('id');
        }

        // 4. Chercher {nom_du_param_id} ex: $user → 'user_id'
        $paramName = $parameter->getName();
        $idField = $paramName . '_id';
        if ($request->attributes->has($idField)) {
            return $request->attributes->get($idField);
        }

        // 5. Chercher par le nom court de la classe : Article → article_id
        $shortName = (new \ReflectionClass($className))->getShortName();
        $snakeId = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . '_id';
        if ($request->attributes->has($snakeId)) {
            return $request->attributes->get($snakeId);
        }

        return null;
    }
}
