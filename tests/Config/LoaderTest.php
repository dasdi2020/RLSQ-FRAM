<?php

declare(strict_types=1);

namespace Tests\Config;

use PHPUnit\Framework\TestCase;
use RLSQ\Config\FileLocator;
use RLSQ\Config\Loader\DelegatingLoader;
use RLSQ\Config\Loader\PhpFileLoader;
use RLSQ\Config\Loader\YamlFileLoader;

class LoaderTest extends TestCase
{
    private FileLocator $locator;

    protected function setUp(): void
    {
        $this->locator = new FileLocator(__DIR__ . '/Fixtures');
    }

    // --- PHP Loader ---

    public function testPhpLoaderSupports(): void
    {
        $loader = new PhpFileLoader($this->locator);

        $this->assertTrue($loader->supports('services.php'));
        $this->assertFalse($loader->supports('config.yaml'));
    }

    public function testPhpLoaderLoad(): void
    {
        $loader = new PhpFileLoader($this->locator);
        $data = $loader->load('services.php');

        $this->assertSame('localhost', $data['parameters']['database.host']);
        $this->assertSame(3306, $data['parameters']['database.port']);
        $this->assertTrue($data['parameters']['app.debug']);
    }

    // --- YAML Loader ---

    public function testYamlLoaderSupports(): void
    {
        $loader = new YamlFileLoader($this->locator);

        $this->assertTrue($loader->supports('config.yaml'));
        $this->assertTrue($loader->supports('config.yml'));
        $this->assertFalse($loader->supports('services.php'));
    }

    public function testYamlLoaderLoad(): void
    {
        $loader = new YamlFileLoader($this->locator);
        $data = $loader->load('config.yaml');

        $this->assertSame('my_secret_key', $data['framework']['secret']);
    }

    // --- Delegating Loader ---

    public function testDelegatingLoader(): void
    {
        $delegating = new DelegatingLoader([
            new PhpFileLoader($this->locator),
            new YamlFileLoader($this->locator),
        ]);

        $this->assertTrue($delegating->supports('services.php'));
        $this->assertTrue($delegating->supports('config.yaml'));
        $this->assertFalse($delegating->supports('config.xml'));

        $phpData = $delegating->load('services.php');
        $this->assertArrayHasKey('parameters', $phpData);

        $yamlData = $delegating->load('config.yaml');
        $this->assertArrayHasKey('framework', $yamlData);
    }

    public function testDelegatingLoaderThrowsOnUnsupported(): void
    {
        $delegating = new DelegatingLoader([
            new PhpFileLoader($this->locator),
        ]);

        $this->expectException(\RuntimeException::class);
        $delegating->load('config.yaml');
    }
}
