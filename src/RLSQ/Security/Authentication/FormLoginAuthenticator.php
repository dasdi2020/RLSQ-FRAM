<?php

declare(strict_types=1);

namespace RLSQ\Security\Authentication;

use RLSQ\HttpFoundation\RedirectResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\Security\Hasher\PasswordHasherInterface;
use RLSQ\Security\User\UserNotFoundException;
use RLSQ\Security\User\UserProviderInterface;

class FormLoginAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly UserProviderInterface $userProvider,
        private readonly PasswordHasherInterface $passwordHasher,
        private readonly string $loginPath = '/login',
        private readonly string $checkPath = '/login',
        private readonly string $defaultTargetPath = '/',
        private readonly string $usernameField = 'username',
        private readonly string $passwordField = 'password',
    ) {}

    public function supports(Request $request): bool
    {
        return $request->getPathInfo() === $this->checkPath
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get($this->usernameField, '');
        $password = $request->request->get($this->passwordField, '');

        if ($username === '' || $password === '') {
            throw new \RuntimeException('Identifiant et mot de passe requis.');
        }

        $user = $this->userProvider->loadUserByIdentifier($username);

        $userPassword = $user->getPassword();
        if ($userPassword === null || !$this->passwordHasher->verify($userPassword, $password)) {
            throw new \RuntimeException('Identifiants invalides.');
        }

        return new Passport($user);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        return new RedirectResponse($this->defaultTargetPath);
    }

    public function onAuthenticationFailure(Request $request, \Throwable $exception): ?Response
    {
        // Stocker le message d'erreur en session si disponible
        if ($request->hasSession()) {
            $request->getSession()->setFlash('error', $exception->getMessage());
        }

        return new RedirectResponse($this->loginPath);
    }
}
