<?php

declare(strict_types=1);

namespace RLSQ\Security\Authentication;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

interface AuthenticatorInterface
{
    /**
     * Vérifie si cet authenticateur supporte la requête.
     */
    public function supports(Request $request): bool;

    /**
     * Authentifie l'utilisateur.
     *
     * @throws \RLSQ\Security\User\UserNotFoundException
     */
    public function authenticate(Request $request): Passport;

    /**
     * Appelé en cas de succès.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response;

    /**
     * Appelé en cas d'échec.
     */
    public function onAuthenticationFailure(Request $request, \Throwable $exception): ?Response;
}
