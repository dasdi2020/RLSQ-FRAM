<?php

declare(strict_types=1);

namespace Tests\Config;

use PHPUnit\Framework\TestCase;
use RLSQ\Config\ConfigCache;

class ConfigCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/rlsq_test_cache_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testWriteAndRead(): void
    {
        $cache = new ConfigCache($this->cacheDir);

        $data = ['key' => 'value', 'nested' => ['a' => 1]];
        $cache->write('test_config', $data);

        $result = $cache->read('test_config');

        $this->assertSame($data, $result);
    }

    public function testReadReturnsNullWhenMissing(): void
    {
        $cache = new ConfigCache($this->cacheDir);

        $this->assertNull($cache->read('nonexistent'));
    }

    public function testIsFreshWhenNotDebug(): void
    {
        $cache = new ConfigCache($this->cacheDir, debug: false);
        $cache->write('config', ['x' => 1]);

        // En mode non-debug, toujours fresh une fois écrit
        $this->assertTrue($cache->isFresh('config'));
    }

    public function testIsFreshChecksSourceInDebug(): void
    {
        $cache = new ConfigCache($this->cacheDir, debug: true);

        $sourceFile = $this->cacheDir . '/source.yaml';
        mkdir($this->cacheDir, 0777, true);
        file_put_contents($sourceFile, 'test');

        // Écrire le cache APRÈS la source
        sleep(1);
        $cache->write('config', ['x' => 1]);

        $this->assertTrue($cache->isFresh('config', [$sourceFile]));

        // Modifier la source APRÈS le cache
        sleep(1);
        file_put_contents($sourceFile, 'modified');
        touch($sourceFile, time() + 2);

        $this->assertFalse($cache->isFresh('config', [$sourceFile]));
    }

    public function testNotFreshWhenNoCache(): void
    {
        $cache = new ConfigCache($this->cacheDir);

        $this->assertFalse($cache->isFresh('nothing'));
    }

    public function testCreatesDirectoryAutomatically(): void
    {
        $deepDir = $this->cacheDir . '/sub/dir';
        $cache = new ConfigCache($deepDir);

        $cache->write('test', ['data' => true]);

        $this->assertDirectoryExists($deepDir);
        $this->assertSame(['data' => true], $cache->read('test'));
    }
}
