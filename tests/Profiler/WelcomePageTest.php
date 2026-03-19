<?php

declare(strict_types=1);

namespace Tests\Profiler;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpKernel\WelcomePage;

class WelcomePageTest extends TestCase
{
    public function testRendersHtml(): void
    {
        $html = WelcomePage::render();

        $this->assertStringContainsString('RLSQ', $html);
        $this->assertStringContainsString('FRAM', $html);
        $this->assertStringContainsString(PHP_VERSION, $html);
        $this->assertStringContainsString('Bienvenue', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function testRendersRoutes(): void
    {
        $routes = [
            'home' => ['path' => '/', 'methods' => ['GET'], 'controller' => 'HomeController::index'],
            'api' => ['path' => '/api', 'methods' => ['GET', 'POST'], 'controller' => 'ApiController::status'],
        ];

        $html = WelcomePage::render($routes);

        $this->assertStringContainsString('home', $html);
        $this->assertStringContainsString('/api', $html);
        $this->assertStringContainsString('HomeController::index', $html);
        $this->assertStringContainsString('GET, POST', $html);
    }

    public function testRendersComponents(): void
    {
        $html = WelcomePage::render();

        $this->assertStringContainsString('HttpFoundation', $html);
        $this->assertStringContainsString('EventDispatcher', $html);
        $this->assertStringContainsString('Routing', $html);
        $this->assertStringContainsString('HttpKernel', $html);
        $this->assertStringContainsString('DI Container', $html);
        $this->assertStringContainsString('Security', $html);
        $this->assertStringContainsString('Database/ORM', $html);
    }

    public function testRendersGettingStarted(): void
    {
        $html = WelcomePage::render();

        $this->assertStringContainsString('Prochaines', $html);
        $this->assertStringContainsString('HomeController', $html);
        $this->assertStringContainsString('#[Route', $html);
    }
}
