<?php

declare(strict_types=1);

namespace RLSQ\Form\Type;

use RLSQ\Form\FormBuilder;

/**
 * Classe de base pour les types de formulaire.
 */
abstract class AbstractType
{
    /**
     * Construit les champs du formulaire.
     */
    public function buildForm(FormBuilder $builder, array $options): void
    {
        // À surcharger
    }

    /**
     * Options par défaut.
     */
    public function configureOptions(): array
    {
        return [];
    }

    /**
     * Nom de bloc utilisé pour le rendu.
     */
    public function getBlockPrefix(): string
    {
        $class = static::class;
        $short = (new \ReflectionClass($class))->getShortName();

        // ContactFormType → contact, ArticleType → article
        $name = preg_replace('/Type$/', '', $short);
        $name = preg_replace('/Form$/', '', $name);

        return strtolower($name);
    }
}
