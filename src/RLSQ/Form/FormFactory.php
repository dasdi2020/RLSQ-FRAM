<?php

declare(strict_types=1);

namespace RLSQ\Form;

use RLSQ\Form\Type\AbstractType;

class FormFactory
{
    /**
     * Crée un formulaire à partir d'un AbstractType.
     */
    public function create(string|AbstractType $type, mixed $data = null, array $options = []): Form
    {
        if (is_string($type)) {
            $type = new $type();
        }

        $mergedOptions = array_merge($type->configureOptions(), $options);
        $name = $mergedOptions['name'] ?? $type->getBlockPrefix();

        $builder = new FormBuilder($name);

        if (isset($mergedOptions['method'])) {
            $builder->setMethod($mergedOptions['method']);
        }
        if (isset($mergedOptions['action'])) {
            $builder->setAction($mergedOptions['action']);
        }

        $type->buildForm($builder, $mergedOptions);

        return new Form(
            $builder->getName(),
            $builder->getMethod(),
            $builder->getAction(),
            $builder->getFields(),
            $data,
        );
    }

    /**
     * Crée un formulaire simple (sans AbstractType).
     */
    public function createBuilder(string $name = 'form'): FormBuilder
    {
        return new FormBuilder($name);
    }

    /**
     * Crée un Form depuis un FormBuilder.
     */
    public function createFromBuilder(FormBuilder $builder, mixed $data = null): Form
    {
        return new Form(
            $builder->getName(),
            $builder->getMethod(),
            $builder->getAction(),
            $builder->getFields(),
            $data,
        );
    }
}
