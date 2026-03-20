<?php

declare(strict_types=1);

namespace Tests\Payment;

use App\Plugin\PaymentPlugin\Gateway\GlobalPaymentsGateway;
use App\Plugin\PaymentPlugin\Gateway\MonerisGateway;
use App\Plugin\PaymentPlugin\Gateway\PayPalGateway;
use App\Plugin\PaymentPlugin\Gateway\StripeGateway;
use App\Plugin\PaymentPlugin\PaymentPlugin;
use App\Plugin\PaymentPlugin\Service\GatewayFactory;
use App\Plugin\PaymentPlugin\Service\PaymentService;
use App\Tenant\Database\TenantBaseMigration;
use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\Migration\MigrationManager;

class PaymentTest extends TestCase
{
    private Connection $conn;
    private PaymentService $service;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');

        $mgr = new MigrationManager($this->conn);
        $mgr->addMigration(new TenantBaseMigration());
        $mgr->migrate();

        // Install payment plugin tables
        $plugin = new PaymentPlugin();
        $plugin->install($this->conn);

        // Configure a test gateway
        $this->conn->execute(
            'INSERT INTO payment_gateway_configs (gateway, credentials, is_active, is_default, settings) VALUES (:gw, :c, 1, 1, :s)',
            ['gw' => 'stripe', 'c' => '{"secret_key":"sk_test_xxx","test_mode":true}', 's' => '{"default_currency":"CAD"}'],
        );

        $this->service = new PaymentService($this->conn);
    }

    // --- Gateways ---

    public function testGatewayFactory(): void
    {
        $stripe = GatewayFactory::create('stripe', ['test_mode' => true]);
        $this->assertInstanceOf(StripeGateway::class, $stripe);
        $this->assertSame('stripe', $stripe->getName());

        $paypal = GatewayFactory::create('paypal', ['test_mode' => true]);
        $this->assertInstanceOf(PayPalGateway::class, $paypal);

        $moneris = GatewayFactory::create('moneris', ['test_mode' => true]);
        $this->assertInstanceOf(MonerisGateway::class, $moneris);

        $gp = GatewayFactory::create('global_payments', ['test_mode' => true]);
        $this->assertInstanceOf(GlobalPaymentsGateway::class, $gp);
    }

    public function testAvailableGateways(): void
    {
        $gateways = GatewayFactory::getAvailableGateways();

        $this->assertContains('stripe', $gateways);
        $this->assertContains('paypal', $gateways);
        $this->assertContains('moneris', $gateways);
        $this->assertContains('global_payments', $gateways);
    }

    public function testGatewayFactoryInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GatewayFactory::create('invalid');
    }

    public function testStripeTestCheckout(): void
    {
        $gw = new StripeGateway(['test_mode' => true]);
        $result = $gw->createCheckout(['amount' => 49.99, 'currency' => 'cad', 'description' => 'Test']);

        $this->assertStringContainsString('checkout.stripe.com', $result['checkout_url']);
        $this->assertTrue($result['test_mode']);
        $this->assertNotEmpty($result['external_id']);
    }

    public function testStripeTestRefund(): void
    {
        $gw = new StripeGateway(['test_mode' => true]);
        $result = $gw->refund('pi_test_123', 10.00, 'Test refund');

        $this->assertSame('completed', $result['status']);
        $this->assertSame(10.00, $result['amount']);
    }

    public function testAllGatewaysCheckout(): void
    {
        foreach (['stripe', 'paypal', 'moneris', 'global_payments'] as $name) {
            $gw = GatewayFactory::create($name, ['test_mode' => true]);
            $result = $gw->createCheckout(['amount' => 25.00]);

            $this->assertArrayHasKey('checkout_url', $result);
            $this->assertArrayHasKey('external_id', $result);
        }
    }

    // --- Payment Service ---

    public function testCreateCheckout(): void
    {
        $result = $this->service->createCheckout([
            'amount' => 99.99,
            'description' => 'Formation PHP',
            'payer_email' => 'alice@test.com',
            'module_source' => 'formations',
            'module_item_id' => 1,
        ]);

        $this->assertArrayHasKey('payment_id', $result);
        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertSame('pending', $result['status']);
    }

    public function testConfirmPayment(): void
    {
        $checkout = $this->service->createCheckout(['amount' => 50.00, 'description' => 'Test']);
        $payment = $this->service->confirm($checkout['payment_id']);

        $this->assertSame('completed', $payment['status']);
        $this->assertNotNull($payment['completed_at']);
    }

    public function testGetPayments(): void
    {
        $this->service->createCheckout(['amount' => 10.00]);
        $this->service->createCheckout(['amount' => 20.00]);

        $result = $this->service->getPayments();

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function testGetPaymentsWithFilter(): void
    {
        $c1 = $this->service->createCheckout(['amount' => 10.00]);
        $this->service->createCheckout(['amount' => 20.00]);
        $this->service->confirm($c1['payment_id']);

        $completed = $this->service->getPayments(1, 20, ['status' => 'completed']);
        $this->assertSame(1, $completed['total']);
    }

    public function testStats(): void
    {
        $c1 = $this->service->createCheckout(['amount' => 100.00]);
        $c2 = $this->service->createCheckout(['amount' => 50.00]);
        $this->service->confirm($c1['payment_id']);

        $stats = $this->service->getStats();

        $this->assertSame(100.0, $stats['total_revenue']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(1, $stats['pending']);
    }

    // --- Refunds ---

    public function testRefundPayment(): void
    {
        $checkout = $this->service->createCheckout(['amount' => 100.00]);
        $this->service->confirm($checkout['payment_id']);

        $refund = $this->service->refund($checkout['payment_id'], 30.00, 'Partial refund');

        $this->assertSame(30.0, (float) $refund['amount']);
        $this->assertSame('completed', $refund['status']);

        $payment = $this->service->getPayment($checkout['payment_id']);
        $this->assertSame('partially_refunded', $payment['status']);
    }

    public function testFullRefund(): void
    {
        $checkout = $this->service->createCheckout(['amount' => 50.00]);
        $this->service->confirm($checkout['payment_id']);
        $this->service->refund($checkout['payment_id'], 50.00, 'Full refund');

        $payment = $this->service->getPayment($checkout['payment_id']);
        $this->assertSame('refunded', $payment['status']);
    }

    public function testRefundHistory(): void
    {
        $checkout = $this->service->createCheckout(['amount' => 100.00]);
        $this->service->confirm($checkout['payment_id']);

        $this->service->refund($checkout['payment_id'], 20.00);
        $this->service->refund($checkout['payment_id'], 30.00);

        $refunds = $this->service->getRefunds($checkout['payment_id']);

        $this->assertCount(2, $refunds);
    }

    public function testRefundPendingPaymentThrows(): void
    {
        $checkout = $this->service->createCheckout(['amount' => 50.00]); // still pending

        $this->expectException(\RuntimeException::class);
        $this->service->refund($checkout['payment_id'], 50.00);
    }

    // --- Subscriptions ---

    public function testCreateSubscription(): void
    {
        $sub = $this->service->createSubscription([
            'member_id' => 1,
            'plan_name' => 'Mensuel',
            'amount' => 29.99,
            'interval_type' => 'monthly',
            'module_source' => 'formations',
        ]);

        $this->assertSame('active', $sub['status']);
        $this->assertSame(1, (int) $sub['auto_renew']);
        $this->assertNotNull($sub['current_period_start']);
        $this->assertNotNull($sub['current_period_end']);
    }

    public function testCancelSubscription(): void
    {
        $sub = $this->service->createSubscription(['plan_name' => 'Test', 'amount' => 10.00]);

        $cancelled = $this->service->cancelSubscription((int) $sub['id']);

        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0, (int) $cancelled['auto_renew']);
        $this->assertNotNull($cancelled['cancelled_at']);
    }

    public function testGetSubscriptions(): void
    {
        $this->service->createSubscription(['plan_name' => 'A', 'amount' => 10.00]);
        $this->service->createSubscription(['plan_name' => 'B', 'amount' => 20.00]);

        $result = $this->service->getSubscriptions();

        $this->assertSame(2, $result['total']);
    }

    // --- Gateway Config ---

    public function testSaveGatewayConfig(): void
    {
        $config = $this->service->saveGatewayConfig([
            'gateway' => 'paypal',
            'credentials' => ['client_id' => 'pp_test', 'secret' => 'pp_secret'],
            'is_active' => true,
        ]);

        $this->assertSame('paypal', $config['gateway']);
    }

    public function testGetGatewayConfigsMasked(): void
    {
        $configs = $this->service->getGatewayConfigs();

        $this->assertCount(1, $configs);
        $this->assertArrayHasKey('credentials_masked', $configs[0]);
        $this->assertArrayNotHasKey('credentials', $configs[0]);
    }

    // --- Plugin ---

    public function testPaymentPluginInfo(): void
    {
        $plugin = new PaymentPlugin();

        $this->assertSame('payments', $plugin->getSlug());
        $this->assertSame('1.0.0', $plugin->getVersion());
        $this->assertCount(3, $plugin->getMenuItems());
        $this->assertNotEmpty($plugin->getSettingsSchema()['fields']);
    }
}
