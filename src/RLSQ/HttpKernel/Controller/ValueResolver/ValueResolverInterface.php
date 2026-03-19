<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel\Controller\ValueResolver;

use RLSQ\HttpFoundation\Request;

/**
 * Interface pour les résolveurs de valeurs d'arguments de contrôleur.
 * Chaque résolveur tente de fournir la valeur d'un paramètre.
 */
interface ValueResolverInterface
{
    /**
     * Tente de résoudre un paramètre.
     * Retourne un tableau de valeurs (généralement 1 élément) ou un tableau vide si non supporté.
     *
     * @return array La valeur résolue dans un tableau, ou [] si non résolu.
     */
    public function resolve(Request $request, \ReflectionParameter $parameter): array;
}
