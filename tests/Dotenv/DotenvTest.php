<?php

declare(strict_types=1);

namespace Tests\Dotenv;

use PHPUnit\Framework\TestCase;
use RLSQ\Dotenv\Dotenv;

class DotenvTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/rlsq_dotenv_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/.env*') as $f) { unlink($f); }
        foreach (glob($this->tmpDir . '/*') as $f) { unlink($f); }
        if (is_dir($this->tmpDir)) { rmdir($this->tmpDir); }

        // Clean up env
        foreach (['APP_ENV', 'APP_DEBUG', 'DB_HOST', 'DB_URL', 'SECRET', 'MY_VAR', 'EXPORTED'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }

    public function testLoadSimpleValues(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=dev\nAPP_DEBUG=true\nDB_HOST=localhost\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('dev', $dotenv->get('APP_ENV'));
        $this->assertSame('true', $dotenv->get('APP_DEBUG'));
        $this->assertSame('localhost', $dotenv->get('DB_HOST'));
    }

    public function testQuotedValues(): void
    {
        file_put_contents($this->tmpDir . '/.env', "MY_VAR=\"hello world\"\nSECRET='s3cr3t'\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('hello world', $dotenv->get('MY_VAR'));
        $this->assertSame('s3cr3t', $dotenv->get('SECRET'));
    }

    public function testInterpolation(): void
    {
        file_put_contents($this->tmpDir . '/.env', "DB_HOST=localhost\nDB_URL=mysql://\${DB_HOST}/mydb\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('mysql://localhost/mydb', $dotenv->get('DB_URL'));
    }

    public function testCommentsIgnored(): void
    {
        file_put_contents($this->tmpDir . '/.env', "# This is a comment\nAPP_ENV=prod\n# Another comment\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('prod', $dotenv->get('APP_ENV'));
        $this->assertFalse($dotenv->has('#'));
    }

    public function testInlineComments(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=dev # development\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('dev', $dotenv->get('APP_ENV'));
    }

    public function testExportPrefix(): void
    {
        file_put_contents($this->tmpDir . '/.env', "export EXPORTED=yes\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('yes', $dotenv->get('EXPORTED'));
    }

    public function testLocalOverrides(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=dev\nSECRET=base\n");
        file_put_contents($this->tmpDir . '/.env.local', "SECRET=local_override\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('dev', $dotenv->get('APP_ENV'));
        $this->assertSame('local_override', $dotenv->get('SECRET'));
    }

    public function testEnvSpecificFile(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=test\n");
        file_put_contents($this->tmpDir . '/.env.test', "DB_HOST=test-db\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('test-db', $dotenv->get('DB_HOST'));
    }

    public function testPushesToGlobals(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=staging\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $this->assertSame('staging', $_ENV['APP_ENV']);
        $this->assertSame('staging', $_SERVER['APP_ENV']);
        $this->assertSame('staging', getenv('APP_ENV'));
    }

    public function testGetDefault(): void
    {
        $dotenv = new Dotenv($this->tmpDir);

        $this->assertNull($dotenv->get('NONEXISTENT'));
        $this->assertSame('fallback', $dotenv->get('NONEXISTENT', 'fallback'));
    }

    public function testAll(): void
    {
        file_put_contents($this->tmpDir . '/.env', "A=1\nB=2\n");

        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load();

        $all = $dotenv->all();
        $this->assertSame('1', $all['A']);
        $this->assertSame('2', $all['B']);
    }

    public function testMissingFileDoesNotThrow(): void
    {
        $dotenv = new Dotenv($this->tmpDir);
        $dotenv->load(); // No .env file

        $this->assertSame([], $dotenv->all());
    }
}
