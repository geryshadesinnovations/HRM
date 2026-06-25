<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Billing\Gateways\ManualGateway;
use App\Domains\Billing\Gateways\RazorpayGateway;
use App\Domains\Billing\Services\GatewayManager;
use Illuminate\Http\Request;
use Tests\TestCase;

final class RazorpayGatewayTest extends TestCase
{
    public function test_webhook_signature_verification(): void
    {
        config(['billing.gateways.razorpay.webhook_secret' => 'whsec_test']);
        $gateway = new RazorpayGateway;

        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_1', 'amount' => 1000]]]]);
        $signature = hash_hmac('sha256', $body, 'whsec_test');

        $valid = Request::create('/webhooks/razorpay', 'POST', [], [], [], [], $body);
        $valid->headers->set('X-Razorpay-Signature', $signature);
        $this->assertTrue($gateway->verifyWebhook($valid));

        $bad = Request::create('/webhooks/razorpay', 'POST', [], [], [], [], $body);
        $bad->headers->set('X-Razorpay-Signature', 'deadbeef');
        $this->assertFalse($gateway->verifyWebhook($bad));
    }

    public function test_webhook_rejected_without_secret(): void
    {
        config(['billing.gateways.razorpay.webhook_secret' => '']);
        $gateway = new RazorpayGateway;

        $req = Request::create('/webhooks/razorpay', 'POST', [], [], [], [], '{}');
        $this->assertFalse($gateway->verifyWebhook($req));
    }

    public function test_parse_event_maps_to_canonical_shape(): void
    {
        $gateway = new RazorpayGateway;

        $event = $gateway->parseEvent([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_abc',
                'amount' => 353882,
                'notes' => ['invoice_number' => 'INV-2026-000001'],
            ]]],
        ]);

        $this->assertSame('payment.captured', $event->type);
        $this->assertSame('pay_abc', $event->gatewayPaymentId);
        $this->assertSame(353882, $event->amountMinor);
        $this->assertSame('INV-2026-000001', $event->invoiceNumber);
        $this->assertSame('pay_abc:payment.captured', $event->eventId);
    }

    public function test_manager_falls_back_to_manual_when_unconfigured(): void
    {
        config(['billing.gateways.razorpay.key_id' => null, 'billing.gateways.razorpay.key_secret' => null]);

        $gateway = app(GatewayManager::class)->gateway('razorpay');
        $this->assertInstanceOf(ManualGateway::class, $gateway);
    }

    public function test_manager_uses_razorpay_when_configured(): void
    {
        config([
            'billing.gateways.razorpay.key_id' => 'rzp_test_x',
            'billing.gateways.razorpay.key_secret' => 'secret_x',
        ]);

        $gateway = app(GatewayManager::class)->gateway('razorpay');
        $this->assertInstanceOf(RazorpayGateway::class, $gateway);

        // Webhook resolution never falls back (signature must match the sender).
        $this->assertInstanceOf(RazorpayGateway::class, app(GatewayManager::class)->forWebhook('razorpay'));
    }
}
