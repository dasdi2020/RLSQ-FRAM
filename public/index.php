<?php

declare(strict_types=1);

use RLSQ\Dotenv\Dotenv;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Controller\ArgumentResolver;
use RLSQ\HttpKernel\Controller\ControllerResolver;
use RLSQ\HttpKernel\EventListener\ExceptionListener;
use RLSQ\HttpKernel\EventListener\RouterListener;
use RLSQ\HttpKernel\HttpKernel;
use RLSQ\HttpKernel\WelcomePage;
use RLSQ\Mailer\Email;
use RLSQ\Mailer\Mailer;
use RLSQ\Mailer\Queue\FilesystemQueue;
use RLSQ\Mailer\Transport\LogTransport;
use RLSQ\Profiler\Collector\EventCollector;
use RLSQ\Profiler\Collector\MailerCollector;
use RLSQ\Profiler\Collector\PerformanceCollector;
use RLSQ\Profiler\Collector\RequestCollector;
use RLSQ\Profiler\Collector\RouteCollector;
use RLSQ\Profiler\Profiler;
use RLSQ\Profiler\ProfilerListener;
use RLSQ\Profiler\TraceableEventDispatcher;
use RLSQ\Profiler\WebDebugToolbar;
use RLSQ\Routing\Matcher\UrlMatcher;
use RLSQ\Routing\Route;
use RLSQ\Routing\RouteCollection;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// --- Environment ---
$dotenv = Dotenv::loadIn(dirname(__DIR__));
$debug = ($dotenv->get('APP_DEBUG', 'true') === 'true');

// --- Profiler ---
$profiler = new Profiler();

// --- Mailer ---
$projectDir = dirname(__DIR__);
$mailQueue = new FilesystemQueue($projectDir . '/var/mail_queue');
$mailer = new Mailer(new LogTransport($projectDir . '/var/mail_log'), $mailQueue);
$mailer->setDefaultFrom($dotenv->get('MAILER_FROM', 'noreply@rlsq-fram.local'));

// --- Routes ---
$routes = new RouteCollection();

$routes->add('home', new Route('/', [
    '_controller' => function () use ($routes): Response {
        $routeInfos = [];
        foreach ($routes->all() as $name => $route) {
            $controller = $route->getController();
            $routeInfos[$name] = [
                'path' => $route->getPath(),
                'methods' => $route->getMethods() ?: ['ANY'],
                'controller' => is_string($controller) ? $controller : (is_callable($controller) ? 'Closure' : 'N/A'),
            ];
        }

        return new Response(WelcomePage::render($routeInfos), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    },
]));

$routes->add('api_status', new Route('/api/status', [
    '_controller' => function () use ($dotenv, $mailQueue): JsonResponse {
        return new JsonResponse([
            'status' => 'ok',
            'framework' => 'RLSQ-FRAM',
            'version' => '0.1.0',
            'php' => PHP_VERSION,
            'env' => $dotenv->get('APP_ENV', 'dev'),
            'mail_queue' => $mailQueue->count(),
        ]);
    },
], ['GET']));

// Exemple : route pour envoyer un email de test (et voir dans le profiler)
$routes->add('api_mail_test', new Route('/api/mail/test', [
    '_controller' => function (Request $request) use ($mailer): JsonResponse {
        $email = (new Email())
            ->to('test@example.com')
            ->subject('Test depuis RLSQ-FRAM')
            ->text('Ceci est un email de test.')
            ->html('<h1>RLSQ-FRAM</h1><p>Email de test envoyé le ' . date('Y-m-d H:i:s') . '</p>');

        $mailer->send($email);

        return new JsonResponse(['status' => 'sent', 'id' => $email->getId()]);
    },
], ['POST']));

$routes->add('api_mail_queue', new Route('/api/mail/queue', [
    '_controller' => function () use ($mailer): JsonResponse {
        $email = (new Email())
            ->to('queued@example.com')
            ->subject('Email en queue')
            ->text('Cet email sera envoyé par le worker.')
            ->priority(2);

        $mailer->queue($email);

        return new JsonResponse(['status' => 'queued', 'id' => $email->getId(), 'pending' => $mailer->getQueue()->count()]);
    },
], ['POST']));

// --- Event Dispatcher ---
$dispatcher = new TraceableEventDispatcher();

$eventCollector = new EventCollector($dispatcher);
$dispatcher->setEventCollector($eventCollector);

$profiler->addCollector(new RequestCollector());
$profiler->addCollector(new RouteCollector());
$profiler->addCollector(new PerformanceCollector($profiler));
$profiler->addCollector($eventCollector);
$profiler->addCollector(new MailerCollector($mailer));

// --- Listeners ---
$dispatcher->addSubscriber(new RouterListener(new UrlMatcher($routes)));
$dispatcher->addSubscriber(new ExceptionListener(debug: $debug));
$dispatcher->addSubscriber(new ProfilerListener($profiler, new WebDebugToolbar(), enabled: $debug));

// --- Kernel ---
$kernel = new HttpKernel($dispatcher, new ControllerResolver(), new ArgumentResolver());

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
