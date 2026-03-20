<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Gateway;

class PayPalGateway extends AbstractGateway
{
    public function getName(): string { return 'paypal'; }

    public function createCheckout(array $params): array
    {
        $orderId = 'PP_' . ($this->testMode ? 'TEST_' : '') . strtoupper(bin2hex(random_bytes(8)));

        if ($this->testMode) {
            return ['checkout_url' => "https://www.sandbox.paypal.com/checkoutnow?token={$orderId}", 'session_id' => $orderId, 'external_id' => $orderId, 'test_mode' => true];
        }

        // Production : POST /v2/checkout/orders via PayPal REST API
        return ['checkout_url' => '', 'session_id' => $orderId, 'external_id' => $orderId];
    }

    public function capturePayment(string $externalId): array
    {
        return ['status' => $this->testMode ? 'completed' : 'pending', 'external_id' => $externalId];
    }

    public function refund(string $externalId, float $amount, string $reason = ''): array
    {
        return ['refund_id' => 'PPR_' . bin2hex(random_bytes(8)), 'status' => 'completed', 'amount' => $amount];
    }

    public function createSubscription(array $params): array
    {
        return ['subscription_id' => 'PPS_' . bin2hex(random_bytes(8)), 'status' => 'active'];
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return ['subscription_id' => $subscriptionId, 'status' => 'cancelled'];
    }

    public function handleWebhook(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];
        return ['event_type' => $data['event_type'] ?? 'unknown', 'data' => $data['resource'] ?? []];
    }
}
