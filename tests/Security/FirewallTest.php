<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use RLSQ\EventDispatcher\EventDispatcher;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\Routing\Matcher\UrlMatcher;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;
use RLSQ\Security\Authentication\FormLoginAuthenticator;
use RLSQ\Security\Authentication\TokenStorage;
use RLSQ\Security\Authentication\UsernamePasswordToken;
use RLSQ\Security\Firewall;
use RLSQ\Security\Hasher\NativePasswordHasher;
use RLSQ\Security\User\InMemoryUser;
use RLSQ\Security\User\InMemoryUserProvider;

class FirewallTest extends TestCase
{
    private TokenStorage $tokenStorage;
    private HttpKernel $kernel;
    private InMemoryUserProvider $userProvider;
    private NativePasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->hasher = new NativePasswordHasher();

        $this->userProvider = new InMemoryUserProvider([
            new InMemoryUser('admin', $this->hasher->hash('admin123'), ['ROLE_ADMIN']),
            new InMemoryUser('user', $this->hasher->hash('user123'), ['ROLE_USER']),
        ]);

        $routes = new RouteCollection();
        $routes->add('home', new Route('/', ['_controller' => fn () => new Response('Home')]));
        $routes->add('admin', new Route('/admin', ['_controller' => fn () => new Response('Admin Panel')]));
        $routes->add('login', new Route('/login', [
            '_controller' => fn () => new Response('Login Form'),
        ], ['GET']));
        $routes->add('login_check', new Route('/login', [
            '_controller' => fn () => new Response('Should not reach'),
        ], ['POST']));

        $authenticator = new FormLoginAuthenticator(
            $this->userProvider,
            $this->hasher,
            loginPath: '/login',
            checkPath: '/login',
            defaultTargetPath: '/',
        );

        $firewall = new Firewall($this->tokenStorage, [$authenticator], '/login');
        $firewall->addAccessRule('^/admin', ['ROLE_ADMIN']);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($firewall);
        $dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
        $dispatcher->addSubscriber(new ExceptionListener());

        $this->kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());
    }

    public function testPublicPageAccessible(): void
    {
        $response = $this->kernel->handle(Request::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Home', $response->getContent());
    }

    public function testProtectedPageRedirectsToLogin(): void
    {
        $response = $this->kernel->handle(Request::create('/admin'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('location'));
    }

    public function testProtectedPageAccessibleWithToken(): void
    {
        // Simuler un utilisateur déjà authentifié
        $admin = new InMemoryUser('admin', null, ['ROLE_ADMIN']);
        $this->tokenStorage->setToken(new UsernamePasswordToken($admin, ['ROLE_ADMIN']));

        $response = $this->kernel->handle(Request::create('/admin'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Admin Panel', $response->getContent());
    }

    public function testProtectedPageDeniedWrongRole(): void
    {
        $user = new InMemoryUser('user', null, ['ROLE_USER']);
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, ['ROLE_USER']));

        $response = $this->kernel->handle(Request::create('/admin'));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testSuccessfulLogin(): void
    {
        $response = $this->kernel->handle(
            Request::create('/login', 'POST', ['username' => 'admin', 'password' => 'admin123']),
        );

        // Redirige vers /
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/', $response->headers->get('location'));

        // Token doit être défini
        $this->assertTrue($this->tokenStorage->isAuthenticated());
        $this->assertSame('admin', $this->tokenStorage->getUser()->getUserIdentifier());
    }

    public function testFailedLogin(): void
    {
        $response = $this->kernel->handle(
            Request::create('/login', 'POST', ['username' => 'admin', 'password' => 'wrong']),
        );

        // Redirige vers /login
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('location'));

        // Pas de token
        $this->assertFalse($this->tokenStorage->isAuthenticated());
    }

    public function testLoginPageGetNotIntercepted(): void
    {
        $response = $this->kernel->handle(Request::create('/login', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Login Form', $response->getContent());
    }
}
