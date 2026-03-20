<?php

declare(strict_types=1);

namespace App\RateLimit;

use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpKernel\Event\RequestEvent;
use RLSQ\HttpKernel\KernelEvents;

class RateLimiter implements EventSubscriberInterface
{
    /** @var array<string, array{count: int, reset_at: int}> */
    private static array $store = [];

    public function __construct(
        private readonly int $maxRequests = 60,
        private readonly int $windowSeconds = 60,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $key = $request->getClientIp() ?? 'unknown';

        $now = time();

        if (!isset(self::$store[$key]) || self::$store[$key]['reset_at'] <= $now) {
            self::$store[$key] = ['count' => 0, 'reset_at' => $now + $this->windowSeconds];
        }

        self::$store[$key]['count']++;

        if (self::$store[$key]['count'] > $this->maxRequests) {
            $retryAfter = self::$store[$key]['reset_at'] - $now;

            $response = new JsonResponse(
                ['error' => 'Trop de requêtes. Réessayez dans ' . $retryAfter . 's.'],
                429,
                ['Retry-After' => (string) $retryAfter, 'X-RateLimit-Limit' => (string) $this->maxRequests],
            );

            $event->setResponse($response);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 100]]; // Très haute priorité
    }

    public static function reset(): void { self::$store = []; }
}
