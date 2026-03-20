<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Gateway;

abstract class AbstractGateway implements GatewayInterface
{
    protected bool $testMode = false;

    public function __construct(
        protected readonly array $credentials = [],
    ) {
        $this->testMode = ($credentials['test_mode'] ?? true) === true;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        return true; // Override dans les implémentations réelles
    }
}
