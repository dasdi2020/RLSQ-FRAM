<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Gateway;

/**
 * Interface unifiée pour tous les providers de paiement.
 */
interface GatewayInterface
{
    public function getName(): string;

    /**
     * Crée une session de paiement (checkout).
     * Retourne : ['checkout_url' => '...', 'session_id' => '...', 'external_id' => '...']
     */
    public function createCheckout(array $params): array;

    /**
     * Capture/confirme un paiement après le checkout.
     * Retourne : ['status' => 'completed|failed', 'external_id' => '...']
     */
    public function capturePayment(string $externalId): array;

    /**
     * Rembourse un paiement (total ou partiel).
     * Retourne : ['refund_id' => '...', 'status' => 'completed|pending']
     */
    public function refund(string $externalId, float $amount, string $reason = ''): array;

    /**
     * Crée un abonnement récurrent.
     * Retourne : ['subscription_id' => '...', 'status' => 'active']
     */
    public function createSubscription(array $params): array;

    /**
     * Annule un abonnement.
     */
    public function cancelSubscription(string $subscriptionId): array;

    /**
     * Vérifie la signature d'un webhook.
     */
    public function verifyWebhook(string $payload, string $signature): bool;

    /**
     * Traite un événement webhook.
     * Retourne : ['event_type' => '...', 'data' => [...]]
     */
    public function handleWebhook(string $payload): array;
}
