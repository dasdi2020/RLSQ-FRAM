<?php

declare(strict_types=1);

namespace Tests\Security\Attribute;

use PHPUnit\Framework\TestCase;
use RLSQ\Controller\Attribute\Route;
use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\Controller\Attribute\AttributeRouteLoader;
use RLSQ\Controller\AbstractController;
use RLSQ\Routing\Matcher\UrlMatcher;
use RLSQ\Security\Attribute\IsGranted;
use RLSQ\Security\Attribute\RequireAuth;
use RLSQ\Security\Authentication\TokenStorage;
use RLSQ\Security\Authentication\UsernamePasswordToken;
use RLSQ\Security\SecurityListener;
use RLSQ\Security\User\InMemoryUser;

class SecurityAttributeTest extends TestCase
{
    private TokenStorage $tokenStorage;

    private function createKernel(string $controllerClass): HttpKernel
    {
        $this->tokenStorage = new TokenStorage();

        $routes = (new AttributeRouteLoader())->load($controllerClass);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new SecurityListener($this->tokenStorage));
        $dispatcher->addSubscriber(new ExceptionListener(debug: true));

        return new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());
    }

    public function testPublicRouteAccessible(): void
    {
        $kernel = $this->createKernel(SecuredController::class);
        $response = $kernel->handle(Request::create('/public'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequireAuthRedirects(): void
    {
        $kernel = $this->createKernel(SecuredController::class);
        $response = $kernel->handle(Request::create('/profile'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('location'));
    }

    public function testRequireAuthAllowsAuthenticated(): void
    {
        $kernel = $this->createKernel(SecuredController::class);
        $this->tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('user', null, ['ROLE_USER']),
            ['ROLE_USER'],
        ));

        $response = $kernel->handle(Request::create('/profile'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testIsGrantedDeniesWrongRole(): void
    {
        $kernel = $this->createKernel(SecuredController::class);
        $this->tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('user', null, ['ROLE_USER']),
            ['ROLE_USER'],
        ));

        $response = $kernel->handle(Request::create('/admin'));
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testIsGrantedAllowsCorrectRole(): void
    {
        $kernel = $this->createKernel(SecuredController::class);
        $this->tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('admin', null, ['ROLE_ADMIN']),
            ['ROLE_ADMIN'],
        ));

        $response = $kernel->handle(Request::create('/admin'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Admin panel', $response->getContent());
    }

    public function testIsGrantedWithoutAuth(): void
    {
        $kernel = $this->createKernel(SecuredController::class);
        $response = $kernel->handle(Request::create('/admin'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testClassLevelIsGranted(): void
    {
        $kernel = $this->createKernel(AdminOnlyController::class);
        $this->tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('user', null, ['ROLE_USER']),
            ['ROLE_USER'],
        ));

        $response = $kernel->handle(Request::create('/admin-area/dashboard'));
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testClassLevelIsGrantedAllows(): void
    {
        $kernel = $this->createKernel(AdminOnlyController::class);
        $this->tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('admin', null, ['ROLE_ADMIN']),
            ['ROLE_ADMIN'],
        ));

        $response = $kernel->handle(Request::create('/admin-area/dashboard'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCustomDeniedMessage(): void
    {
        $kernel = $this->createKernel(SecuredController::class);
        $this->tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('user', null, ['ROLE_USER']),
            ['ROLE_USER'],
        ));

        $response = $kernel->handle(Request::create('/editor'));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('éditeurs', $response->getContent());
    }
}

// --- Fixtures ---

class SecuredController
{
    #[Route('/public', name: 'public')]
    public function publicPage(): Response
    {
        return new Response('Public');
    }

    #[RequireAuth(redirectTo: '/login')]
    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        return new Response('Profile');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin', name: 'admin')]
    public function admin(): Response
    {
        return new Response('Admin panel');
    }

    #[IsGranted('ROLE_EDITOR', message: 'Réservé aux éditeurs.')]
    #[Route('/editor', name: 'editor')]
    public function editor(): Response
    {
        return new Response('Editor');
    }
}

#[IsGranted('ROLE_ADMIN')]
class AdminOnlyController
{
    #[Route('/admin-area/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        return new Response('Dashboard');
    }
}
