<?php

declare(strict_types=1);

namespace RLSQ\Config\Loader;

use RLSQ\Config\FileLocator;
use RLSQ\Config\Yaml\YamlParser;

class YamlFileLoader implements LoaderInterface
{
    private YamlParser $parser;

    public function __construct(
        private readonly FileLocator $locator,
    ) {
        $this->parser = new YamlParser();
    }

    public function load(string $resource): array
    {
        $path = $this->locator->locate($resource);

        return $this->parser->parseFile($path);
    }

    public function supports(string $resource): bool
    {
        $ext = pathinfo($resource, PATHINFO_EXTENSION);

        return in_array($ext, ['yaml', 'yml'], true);
    }
}
