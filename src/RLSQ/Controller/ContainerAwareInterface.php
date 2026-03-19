<?php

declare(strict_types=1);

namespace RLSQ\Controller;

use RLSQ\DependencyInjection\ContainerInterface;

interface ContainerAwareInterface
{
    public function setContainer(ContainerInterface $container): void;
}
