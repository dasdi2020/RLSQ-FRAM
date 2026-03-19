<?php

declare(strict_types=1);

namespace RLSQ\Config\Loader;

use RLSQ\Config\Exception\FileNotFoundException;
use RLSQ\Config\FileLocator;

class PhpFileLoader implements LoaderInterface
{
    public function __construct(
        private readonly FileLocator $locator,
    ) {}

    public function load(string $resource): array
    {
        $path = $this->locator->locate($resource);

        $result = require $path;

        if (!is_array($result)) {
            throw new \RuntimeException(sprintf('Le fichier "%s" doit retourner un tableau.', $path));
        }

        return $result;
    }

    public function supports(string $resource): bool
    {
        return str_ends_with($resource, '.php');
    }
}
