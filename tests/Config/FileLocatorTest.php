<?php

declare(strict_types=1);

namespace Tests\Config;

use PHPUnit\Framework\TestCase;
use RLSQ\Config\Exception\FileNotFoundException;
use RLSQ\Config\FileLocator;

class FileLocatorTest extends TestCase
{
    public function testLocateInDirectory(): void
    {
        $locator = new FileLocator(__DIR__ . '/Fixtures');

        $path = $locator->locate('config.yaml');

        $this->assertStringEndsWith('config.yaml', $path);
        $this->assertFileExists($path);
    }

    public function testLocateAbsolutePath(): void
    {
        $absolutePath = __DIR__ . '/Fixtures/config.yaml';
        $locator = new FileLocator();

        $path = $locator->locate($absolutePath);

        $this->assertSame($absolutePath, $path);
    }

    public function testLocateThrowsOnMissing(): void
    {
        $locator = new FileLocator(__DIR__ . '/Fixtures');

        $this->expectException(FileNotFoundException::class);
        $locator->locate('nonexistent.yaml');
    }

    public function testLocateMultiplePaths(): void
    {
        $locator = new FileLocator([
            __DIR__ . '/NonExistentDir',
            __DIR__ . '/Fixtures',
        ]);

        $path = $locator->locate('config.yaml');

        $this->assertFileExists($path);
    }
}
