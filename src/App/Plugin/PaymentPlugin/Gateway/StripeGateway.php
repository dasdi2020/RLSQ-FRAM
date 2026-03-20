<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Gateway;

/**
 * Gateway Stripe. En mode test, simule les réponses.
 * En production, utiliserait l'API REST Stripe via cURL.
 */
class StripeGateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function createCheckout(array $params): array
    {
        $sessionId = 'cs_' . ($this->testMode ? 'test_' : '') . bin2hex(random_bytes(16));

        if ($this->testMode) {
            return [
                'checkout_url' => "https://checkout.stripe.com/pay/{$sessionId}",
                'session_id' => $sessionId,
                'external_id' => 'pi_' . bin2hex(random_bytes(12)),
                'test_mode' => true,
            ];
        }

        // Production : appel API Stripe via cURL
        // POST https://api.stripe.com/v1/checkout/sessions
        return $this->apiCall('POST', '/v1/checkout/sessions', [
            'line_items' => [['price_data' => [
                'currency' => $params['currency'] ?? 'cad',
                'unit_amount' => (int) (($params['amount'] ?? 0) * 100),
                'product_data' => ['name' => $params['description'] ?? 'Paiement'],
            ], 'quantity' => 1]],
            'mode' => 'payment',
            'success_url' => $params['success_url'] ?? '',
            'cancel_url' => $params['cancel_url'] ?? '',
        ]);
    }

    public function capturePayment(string $externalId): array
    {
        if ($this->testMode) {
            return ['status' => 'completed', 'external_id' => $externalId];
        }

        return $this->apiCall('POST', "/v1/payment_intents/{$externalId}/capture");
    }

    public function refund(string $externalId, float $amount, string $reason = ''): array
    {
        if ($this->testMode) {
            return ['refund_id' => 're_test_' . bin2hex(random_bytes(8)), 'status' => 'completed', 'amount' => $amount];
        }

        return $this->apiCall('POST', '/v1/refunds', [
            'payment_intent' => $externalId,
            'amount' => (int) ($amount * 100),
            'reason' => $reason ?: 'requested_by_customer',
        ]);
    }

    public function createSubscription(array $params): array
    {
        $subId = 'sub_' . ($this->testMode ? 'test_' : '') . bin2hex(random_bytes(12));

        if ($this->testMode) {
            return ['subscription_id' => $subId, 'status' => 'active', 'test_mode' => true];
        }

        return $this->apiCall('POST', '/v1/subscriptions', $params);
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        if ($this->testMode) {
            return ['subscription_id' => $subscriptionId, 'status' => 'cancelled'];
        }

        return $this->apiCall('DELETE', "/v1/subscriptions/{$subscriptionId}");
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        if ($this->testMode) {
            return true;
        }

        $secret = $this->credentials['webhook_secret'] ?? '';
        $timestamp = time();
        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        return [
            'event_type' => $data['type'] ?? 'unknown',
            'data' => $data['data']['object'] ?? [],
        ];
    }

    private function apiCall(string $method, string $endpoint, array $params = []): array
    {
        $apiKey = $this->credentials['secret_key'] ?? '';
        $baseUrl = 'https://api.stripe.com';

        $ch = curl_init($baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$apiKey}", 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if (!empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?: [];
    }
}
