<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Gateway;

class GlobalPaymentsGateway extends AbstractGateway
{
    public function getName(): string { return 'global_payments'; }

    public function createCheckout(array $params): array
    {
        $txnId = 'GP_' . ($this->testMode ? 'TEST_' : '') . strtoupper(bin2hex(random_bytes(8)));

        return ['checkout_url' => "https://pay.globalpayments.com/pay/{$txnId}", 'session_id' => $txnId, 'external_id' => $txnId, 'test_mode' => $this->testMode];
    }

    public function capturePayment(string $externalId): array
    {
        return ['status' => 'completed', 'external_id' => $externalId];
    }

    public function refund(string $externalId, float $amount, string $reason = ''): array
    {
        return ['refund_id' => 'GPR_' . bin2hex(random_bytes(8)), 'status' => 'completed', 'amount' => $amount];
    }

    public function createSubscription(array $params): array
    {
        return ['subscription_id' => 'GPS_' . bin2hex(random_bytes(8)), 'status' => 'active'];
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return ['subscription_id' => $subscriptionId, 'status' => 'cancelled'];
    }

    public function handleWebhook(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];
        return ['event_type' => $data['event'] ?? 'unknown', 'data' => $data];
    }
}
