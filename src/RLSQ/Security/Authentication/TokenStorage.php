<?php

declare(strict_types=1);

namespace RLSQ\Security\Authentication;

/**
 * Stocke le token de l'utilisateur authentifié pour la requête courante.
 */
class TokenStorage
{
    private ?TokenInterface $token = null;

    public function getToken(): ?TokenInterface
    {
        return $this->token;
    }

    public function setToken(?TokenInterface $token): void
    {
        $this->token = $token;
    }

    public function getUser(): ?\RLSQ\Security\User\UserInterface
    {
        return $this->token?->getUser();
    }

    public function isAuthenticated(): bool
    {
        return $this->token !== null && $this->token->isAuthenticated();
    }
}
