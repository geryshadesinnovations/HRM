<?php

declare(strict_types=1);

namespace App\Domains\Billing\Gateways;

use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Support\NormalizedEvent;
use App\Platform\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Reference adapter for manual/offline settlement and as the testable default.
 * Real provider adapters (Razorpay/Cashfree/PayU) implement the same contract
 * and are selected via billing.default_gateway — no business-code change.
 *
 * Webhook auth uses a shared secret header; the canonical event is read from a
 * simple, documented JSON envelope.
 *
 * See docs/04-BILLING.md.
 */
final class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function createOrder(Money $amount, array $meta = []): array
    {
        return [
            'gateway' => $this->name(),
            'order_id' => 'manual_'.Str::uuid()->toString(),
            'amount' => $amount->minor,
            'currency' => $amount->currency,
            'meta' => $meta,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $expected = (string) config('billing.gateways.manual.webhook_secret', env('MANUAL_WEBHOOK_SECRET', ''));

        // When no secret is configured (local/dev), accept; otherwise require a match.
        if ($expected === '') {
            return true;
        }

        return hash_equals($expected, (string) $request->header('X-Webhook-Secret'));
    }

    public function parseEvent(array $payload): NormalizedEvent
    {
        return new NormalizedEvent(
            eventId: (string) ($payload['event_id'] ?? Str::uuid()->toString()),
            type: (string) ($payload['type'] ?? 'payment.captured'),
            gatewayPaymentId: $payload['payment_id'] ?? null,
            amountMinor: (int) ($payload['amount'] ?? 0),
            invoiceNumber: $payload['invoice_number'] ?? null,
            raw: $payload,
        );
    }
}
