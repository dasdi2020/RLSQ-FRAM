<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection\Compiler;

use RLSQ\DependencyInjection\ContainerBuilder;

class Compiler
{
    /** @var CompilerPassInterface[] */
    private array $passes = [];

    public function addPass(CompilerPassInterface $pass): void
    {
        $this->passes[] = $pass;
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getPasses(): array
    {
        return $this->passes;
    }

    public function compile(ContainerBuilder $container): void
    {
        foreach ($this->passes as $pass) {
            $pass->process($container);
        }
    }
}
