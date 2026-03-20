<?php

declare(strict_types=1);

namespace App\Controller;

use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\Database\Connection;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;
use RLSQ\Security\Hasher\PasswordHasherInterface;
use RLSQ\Security\Jwt\JwtManager;
use RLSQ\Security\TwoFactor\EmailCodeSender;
use RLSQ\Security\TwoFactor\TwoFactorManager;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    #[ApiRoute(summary: 'Étape 1 — Login credentials → envoie code 2FA', tags: ['Auth'], responses: [200 => 'Code envoyé', 401 => 'Identifiants invalides'])]
    public function login(
        Request $request,
        Connection $connection,
        PasswordHasherInterface $hasher,
        TwoFactorManager $twoFactor,
        EmailCodeSender $codeSender,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            return $this->json(['error' => 'Email et mot de passe requis.'], 400);
        }

        $user = $connection->fetchOne('SELECT * FROM users WHERE email = :email AND is_active = 1', ['email' => $email]);

        if ($user === false || !$hasher->verify($user['password_hash'], $password)) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        // Générer et envoyer le code 2FA
        $code = $twoFactor->generateCode((int) $user['id'], $email);
        $codeSender->send($email, $code);

        return $this->json([
            'message' => 'Code de vérification envoyé.',
            'requires_2fa' => true,
            'user_id' => (int) $user['id'],
        ]);
    }

    #[Route('/verify-2fa', name: 'auth_verify_2fa', methods: ['POST'])]
    #[ApiRoute(summary: 'Étape 2 — Vérifier code 2FA → JWT', tags: ['Auth'], responses: [200 => 'JWT token', 401 => 'Code invalide'])]
    public function verify2fa(
        Request $request,
        Connection $connection,
        TwoFactorManager $twoFactor,
        JwtManager $jwt,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $userId = (int) ($data['user_id'] ?? 0);
        $code = $data['code'] ?? '';

        if ($userId === 0 || $code === '') {
            return $this->json(['error' => 'user_id et code requis.'], 400);
        }

        if (!$twoFactor->verifyCode($userId, $code)) {
            return $this->json(['error' => 'Code invalide ou expiré.'], 401);
        }

        $user = $connection->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]);

        if ($user === false) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        $roles = json_decode($user['roles'] ?? '[]', true) ?: ['ROLE_USER'];

        $accessToken = $jwt->createToken([
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'sub' => $user['email'],
            'roles' => $roles,
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
        ]);

        $refreshToken = $jwt->createRefreshToken([
            'user_id' => (int) $user['id'],
            'sub' => $user['email'],
        ]);

        return $this->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $jwt->getTtl(),
            'user' => [
                'id' => (int) $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'roles' => $roles,
            ],
        ]);
    }

    #[Route('/refresh', name: 'auth_refresh', methods: ['POST'])]
    #[ApiRoute(summary: 'Refresh du JWT', tags: ['Auth'])]
    public function refresh(Request $request, JwtManager $jwt, Connection $connection): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $refreshToken = $data['refresh_token'] ?? '';

        $payload = $jwt->validateRefreshToken($refreshToken);

        if ($payload === null) {
            return $this->json(['error' => 'Refresh token invalide ou expiré.'], 401);
        }

        $user = $connection->fetchOne('SELECT * FROM users WHERE id = :id AND is_active = 1', ['id' => $payload['user_id']]);

        if ($user === false) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 401);
        }

        $roles = json_decode($user['roles'] ?? '[]', true) ?: ['ROLE_USER'];

        $newAccessToken = $jwt->createToken([
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'sub' => $user['email'],
            'roles' => $roles,
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
        ]);

        return $this->json([
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => $jwt->getTtl(),
        ]);
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Profil utilisateur connecté', tags: ['Auth'])]
    public function me(Request $request): JsonResponse
    {
        $payload = $request->attributes->get('_jwt_payload', []);

        $userId = $payload['user_id'] ?? null;
        $conn = $this->get(Connection::class);
        $user = $userId ? $conn->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]) : null;

        return $this->json([
            'user_id' => $userId,
            'email' => $payload['email'] ?? null,
            'first_name' => $payload['first_name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'roles' => $payload['roles'] ?? [],
            'mfa_method' => $user['mfa_method'] ?? 'email',
            'totp_enabled' => !empty($user['totp_secret']),
        ]);
    }

    #[Route('/mfa/setup-totp', name: 'auth_setup_totp', methods: ['POST'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Configurer TOTP (Google/Microsoft Authenticator)', tags: ['Auth'])]
    public function setupTotp(Request $request, Connection $connection): JsonResponse
    {
        $userId = $request->attributes->get('_user_id');
        $user = $connection->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        $totp = new \RLSQ\Security\TwoFactor\TotpManager();
        $secret = $totp->generateSecret();
        $uri = $totp->getProvisioningUri($secret, $user['email']);
        $qrUrl = $totp->getQrCodeUrl($uri);

        // Sauvegarder le secret temporairement (sera confirmé après vérification)
        $connection->execute(
            'UPDATE users SET totp_secret_pending = :s WHERE id = :id',
            ['s' => $secret, 'id' => $userId],
        );

        return $this->json([
            'secret' => $secret,
            'provisioning_uri' => $uri,
            'qr_code_url' => $qrUrl,
        ]);
    }

    #[Route('/mfa/confirm-totp', name: 'auth_confirm_totp', methods: ['POST'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Confirmer le TOTP avec un code', tags: ['Auth'])]
    public function confirmTotp(Request $request, Connection $connection): JsonResponse
    {
        $userId = $request->attributes->get('_user_id');
        $data = json_decode($request->getContent(), true) ?? [];
        $code = $data['code'] ?? '';

        $user = $connection->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        $pendingSecret = $user['totp_secret_pending'] ?? '';

        if (!$pendingSecret) {
            return $this->json(['error' => 'Aucune configuration TOTP en attente.'], 400);
        }

        $totp = new \RLSQ\Security\TwoFactor\TotpManager();

        if (!$totp->verify($pendingSecret, $code)) {
            return $this->json(['error' => 'Code invalide. Réessayez.'], 400);
        }

        // Activer le TOTP
        $connection->execute(
            'UPDATE users SET totp_secret = :s, totp_secret_pending = NULL, mfa_method = :m WHERE id = :id',
            ['s' => $pendingSecret, 'm' => 'totp', 'id' => $userId],
        );

        return $this->json(['message' => 'TOTP activé avec succès.', 'mfa_method' => 'totp']);
    }

    #[Route('/mfa/switch', name: 'auth_mfa_switch', methods: ['POST'])]
    #[RequireAuth]
    #[ApiRoute(summary: 'Changer la méthode MFA (email ou totp)', tags: ['Auth'])]
    public function switchMfa(Request $request, Connection $connection): JsonResponse
    {
        $userId = $request->attributes->get('_user_id');
        $data = json_decode($request->getContent(), true) ?? [];
        $method = $data['method'] ?? 'email';

        if (!in_array($method, ['email', 'totp'], true)) {
            return $this->json(['error' => 'Méthode invalide. Utilisez "email" ou "totp".'], 400);
        }

        if ($method === 'totp') {
            $user = $connection->fetchOne('SELECT totp_secret FROM users WHERE id = :id', ['id' => $userId]);
            if (empty($user['totp_secret'])) {
                return $this->json(['error' => 'Configurez d\'abord le TOTP via /mfa/setup-totp.'], 400);
            }
        }

        $connection->execute('UPDATE users SET mfa_method = :m WHERE id = :id', ['m' => $method, 'id' => $userId]);

        return $this->json(['message' => 'Méthode MFA changée.', 'mfa_method' => $method]);
    }
}
