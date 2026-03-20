<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Gateway;

class MonerisGateway extends AbstractGateway
{
    public function getName(): string { return 'moneris'; }

    public function createCheckout(array $params): array
    {
        $txnId = 'MON_' . ($this->testMode ? 'TEST_' : '') . strtoupper(bin2hex(random_bytes(8)));
        $host = $this->testMode ? 'esqa.moneris.com' : 'www3.moneris.com';

        return ['checkout_url' => "https://{$host}/HPPtoken/index.php?ticket={$txnId}", 'session_id' => $txnId, 'external_id' => $txnId, 'test_mode' => $this->testMode];
    }

    public function capturePayment(string $externalId): array
    {
        return ['status' => 'completed', 'external_id' => $externalId];
    }

    public function refund(string $externalId, float $amount, string $reason = ''): array
    {
        return ['refund_id' => 'MONR_' . bin2hex(random_bytes(8)), 'status' => 'completed', 'amount' => $amount];
    }

    public function createSubscription(array $params): array
    {
        return ['subscription_id' => 'MONS_' . bin2hex(random_bytes(8)), 'status' => 'active'];
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return ['subscription_id' => $subscriptionId, 'status' => 'cancelled'];
    }

    public function handleWebhook(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];
        return ['event_type' => $data['type'] ?? 'unknown', 'data' => $data];
    }
}
