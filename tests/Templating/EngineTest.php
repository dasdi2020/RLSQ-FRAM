<?php

declare(strict_types=1);

namespace Tests\Templating;

use PHPUnit\Framework\TestCase;
use RLSQ\Templating\Engine;
use RLSQ\Templating\Loader\FilesystemLoader;

class EngineTest extends TestCase
{
    private Engine $engine;

    protected function setUp(): void
    {
        $this->engine = new Engine(
            new FilesystemLoader(__DIR__ . '/Fixtures'),
        );
    }

    // --- Rendu simple ---

    public function testSimpleVariable(): void
    {
        $result = $this->engine->render('simple.html', ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    // --- Échappement XSS ---

    public function testAutoEscape(): void
    {
        $result = $this->engine->render('escape.html', ['content' => '<script>alert("xss")</script>']);

        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRawFilter(): void
    {
        $result = $this->engine->render('escape.html', ['content' => '<b>bold</b>']);

        // La ligne |raw ne doit PAS échapper
        $this->assertStringContainsString('<b>bold</b>', $result);
    }

    // --- Filtres ---

    public function testFilters(): void
    {
        $result = $this->engine->render('filters.html', ['name' => 'hello']);
        $lines = array_filter(array_map('trim', explode("\n", $result)));
        $lines = array_values($lines);

        $this->assertSame('HELLO', $lines[0]);   // upper
        $this->assertSame('hello', $lines[1]);   // lower
        $this->assertSame('Hello', $lines[2]);   // capitalize
        $this->assertSame('5', $lines[3]);        // length
    }

    public function testDefaultFilter(): void
    {
        $result = $this->engine->render('default_filter.html', []);

        $this->assertSame('Anonymous', trim($result));
    }

    public function testDefaultFilterWithValue(): void
    {
        $result = $this->engine->render('default_filter.html', ['name' => 'Alice']);

        $this->assertSame('Alice', trim($result));
    }

    // --- Conditions ---

    public function testIfAdmin(): void
    {
        $result = $this->engine->render('condition.html', ['admin' => true, 'user' => false]);

        $this->assertSame('Admin', $result);
    }

    public function testIfUser(): void
    {
        $result = $this->engine->render('condition.html', ['admin' => false, 'user' => true]);

        $this->assertSame('User', $result);
    }

    public function testIfGuest(): void
    {
        $result = $this->engine->render('condition.html', ['admin' => false, 'user' => false]);

        $this->assertSame('Guest', $result);
    }

    // --- Boucles ---

    public function testForLoop(): void
    {
        $result = $this->engine->render('loop.html', ['items' => ['A', 'B', 'C']]);

        $this->assertStringContainsString('<li>A</li>', $result);
        $this->assertStringContainsString('<li>B</li>', $result);
        $this->assertStringContainsString('<li>C</li>', $result);
    }

    public function testForLoopWithKey(): void
    {
        $result = $this->engine->render('loop_key.html', ['data' => ['x' => 1, 'y' => 2]]);

        $this->assertStringContainsString('x=1', $result);
        $this->assertStringContainsString('y=2', $result);
    }

    public function testForLoopElse(): void
    {
        $result = $this->engine->render('loop_else.html', ['items' => []]);
        $this->assertSame('Vide', trim($result));

        $result = $this->engine->render('loop_else.html', ['items' => ['A']]);
        $this->assertStringContainsString('A', $result);
    }

    // --- Accès par point ---

    public function testDotAccess(): void
    {
        $result = $this->engine->render('dotaccess.html', [
            'user' => ['name' => 'Alice', 'email' => 'alice@test.com'],
        ]);

        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('alice@test.com', $result);
    }

    // --- Commentaires ---

    public function testCommentsHidden(): void
    {
        $result = $this->engine->render('comment.html');

        $this->assertSame('BeforeAfter', $result);
    }

    // --- Include ---

    public function testInclude(): void
    {
        $result = $this->engine->render('include_partial.html', ['name' => 'Bob']);

        $this->assertStringContainsString('Header', $result);
        $this->assertStringContainsString('--Partial:Bob--', $result);
        $this->assertStringContainsString('Footer', $result);
    }

    // --- Héritage (extends/block) ---

    public function testExtends(): void
    {
        $result = $this->engine->render('child.html', [
            'page_title' => 'Mon titre',
            'heading' => 'Bienvenue',
        ]);

        $this->assertStringContainsString('<html>', $result);
        $this->assertStringContainsString('<title>Mon titre</title>', $result);
        $this->assertStringContainsString('<h1>Bienvenue</h1>', $result);
    }

    // --- exists ---

    public function testExists(): void
    {
        $this->assertTrue($this->engine->exists('simple.html'));
        $this->assertFalse($this->engine->exists('nonexistent.html'));
    }
}
