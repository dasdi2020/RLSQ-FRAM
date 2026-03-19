<?php

declare(strict_types=1);

namespace RLSQ\Security\Jwt;

use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\HttpKernel\Event\RequestEvent;
use RLSQ\HttpKernel\KernelEvents;
use RLSQ\Security\Authentication\TokenStorage;
use RLSQ\Security\Authentication\UsernamePasswordToken;
use RLSQ\Security\User\InMemoryUser;

/**
 * Écoute kernel.request pour authentifier via Bearer JWT.
 * Priorité haute (48) pour s'exécuter avant le RouterListener (32).
 */
class JwtListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly JwtManager $jwtManager,
        private readonly TokenStorage $tokenStorage,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $authHeader = $request->headers->get('authorization');

        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            return;
        }

        $tokenString = substr($authHeader, 7);
        $payload = $this->jwtManager->validateAccessToken($tokenString);

        if ($payload === null) {
            return;
        }

        // Créer un token d'authentification depuis le payload JWT
        $roles = $payload['roles'] ?? ['ROLE_USER'];
        $user = new InMemoryUser(
            $payload['sub'] ?? $payload['email'] ?? 'unknown',
            null,
            $roles,
        );

        $token = new UsernamePasswordToken($user, $roles);
        $this->tokenStorage->setToken($token);

        // Stocker les infos du JWT dans les attributs de la requête
        $request->attributes->set('_jwt_payload', $payload);
        $request->attributes->set('_user_id', $payload['user_id'] ?? null);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 48],
        ];
    }
}
