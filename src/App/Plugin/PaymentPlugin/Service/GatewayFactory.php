<?php

declare(strict_types=1);

namespace App\Plugin\PaymentPlugin\Service;

use App\Plugin\PaymentPlugin\Gateway\GatewayInterface;
use App\Plugin\PaymentPlugin\Gateway\GlobalPaymentsGateway;
use App\Plugin\PaymentPlugin\Gateway\MonerisGateway;
use App\Plugin\PaymentPlugin\Gateway\PayPalGateway;
use App\Plugin\PaymentPlugin\Gateway\StripeGateway;

class GatewayFactory
{
    public static function create(string $gateway, array $credentials = []): GatewayInterface
    {
        return match ($gateway) {
            'stripe' => new StripeGateway($credentials),
            'paypal' => new PayPalGateway($credentials),
            'moneris' => new MonerisGateway($credentials),
            'global_payments' => new GlobalPaymentsGateway($credentials),
            default => throw new \InvalidArgumentException("Gateway \"{$gateway}\" non supporté."),
        };
    }

    /** @return string[] */
    public static function getAvailableGateways(): array
    {
        return ['stripe', 'paypal', 'moneris', 'global_payments'];
    }
}
