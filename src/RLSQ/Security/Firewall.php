<?php

declare(strict_types=1);

namespace RLSQ\Security;

use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\HttpFoundation\RedirectResponse;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Event\RequestEvent;
use RLSQ\HttpKernel\KernelEvents;
use RLSQ\Security\Authentication\AuthenticatorInterface;
use RLSQ\Security\Authentication\TokenStorage;
use RLSQ\Security\Authentication\UsernamePasswordToken;

/**
 * Event listener sur kernel.request.
 * Gère l'authentification et la protection des zones sécurisées.
 */
class Firewall implements EventSubscriberInterface
{
    /** @var AuthenticatorInterface[] */
    private array $authenticators;

    /** @var array<array{pattern: string, roles: string[]}> */
    private array $accessRules = [];

    private string $loginPath;

    /**
     * @param AuthenticatorInterface[] $authenticators
     */
    public function __construct(
        private readonly TokenStorage $tokenStorage,
        array $authenticators = [],
        string $loginPath = '/login',
    ) {
        $this->authenticators = $authenticators;
        $this->loginPath = $loginPath;
    }

    public function addAuthenticator(AuthenticatorInterface $authenticator): void
    {
        $this->authenticators[] = $authenticator;
    }

    /**
     * Ajoute une règle d'accès : pattern URL → rôles requis.
     *
     * @param string   $pattern Regex ou préfixe (ex: "^/admin")
     * @param string[] $roles   Rôles requis
     */
    public function addAccessRule(string $pattern, array $roles): void
    {
        $this->accessRules[] = ['pattern' => $pattern, 'roles' => $roles];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // 1. Tenter l'authentification avec chaque authenticateur
        foreach ($this->authenticators as $authenticator) {
            if (!$authenticator->supports($request)) {
                continue;
            }

            try {
                $passport = $authenticator->authenticate($request);
                $user = $passport->getUser();

                $token = new UsernamePasswordToken($user, $user->getRoles());
                $this->tokenStorage->setToken($token);

                $response = $authenticator->onAuthenticationSuccess($request, $token);

                if ($response !== null) {
                    $event->setResponse($response);
                    return;
                }
            } catch (\Throwable $e) {
                $response = $authenticator->onAuthenticationFailure($request, $e);

                if ($response !== null) {
                    $event->setResponse($response);
                    return;
                }
            }
        }

        // 2. Restaurer le token depuis la session si disponible
        if ($this->tokenStorage->getToken() === null && $request->hasSession()) {
            $session = $request->getSession();
            $token = $session->get('_security_token');
            if ($token instanceof \RLSQ\Security\Authentication\TokenInterface) {
                $this->tokenStorage->setToken($token);
            }
        }

        // 3. Vérifier les règles d'accès
        $path = $request->getPathInfo();

        foreach ($this->accessRules as $rule) {
            if (!preg_match('#' . $rule['pattern'] . '#', $path)) {
                continue;
            }

            // Cette URL est protégée
            $token = $this->tokenStorage->getToken();

            if ($token === null || !$token->isAuthenticated()) {
                $event->setResponse(new RedirectResponse($this->loginPath));
                return;
            }

            // Vérifier les rôles
            $userRoles = $token->getRoles();
            $hasRole = false;

            foreach ($rule['roles'] as $requiredRole) {
                if (in_array($requiredRole, $userRoles, true)) {
                    $hasRole = true;
                    break;
                }
            }

            if (!$hasRole) {
                $event->setResponse(new Response('Accès interdit.', 403));
                return;
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 64],
        ];
    }
}
