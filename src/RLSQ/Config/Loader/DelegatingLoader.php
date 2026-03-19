<?php

declare(strict_types=1);

namespace RLSQ\Config\Loader;

/**
 * Délègue le chargement au premier loader qui supporte la ressource.
 */
class DelegatingLoader implements LoaderInterface
{
    /** @var LoaderInterface[] */
    private array $loaders;

    /**
     * @param LoaderInterface[] $loaders
     */
    public function __construct(array $loaders)
    {
        $this->loaders = $loaders;
    }

    public function load(string $resource): array
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($resource)) {
                return $loader->load($resource);
            }
        }

        throw new \RuntimeException(sprintf('Aucun loader ne supporte la ressource "%s".', $resource));
    }

    public function supports(string $resource): bool
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($resource)) {
                return true;
            }
        }

        return false;
    }
}
