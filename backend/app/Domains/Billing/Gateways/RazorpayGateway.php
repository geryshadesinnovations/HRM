<?php

declare(strict_types=1);

namespace App\Domains\Billing\Gateways;

use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Support\NormalizedEvent;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use App\Platform\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Real Razorpay adapter.
 *
 *  - createOrder()   → POST https://api.razorpay.com/v1/orders (HTTP Basic auth).
 *  - verifyWebhook() → HMAC-SHA256 of the RAW body with the webhook secret,
 *                      compared to the X-Razorpay-Signature header.
 *  - parseEvent()    → maps Razorpay's event envelope to our NormalizedEvent.
 *
 * Credentials live in config/billing.php (gateways.razorpay.*). When they are
 * absent, GatewayManager falls back to the manual adapter for order creation.
 *
 * See docs/04-BILLING.md.
 */
final class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function name(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->keyId()) && ! empty($this->keySecret());
    }

    public function createOrder(Money $amount, array $meta = []): array
    {
        if (! $this->isConfigured()) {
            throw new ApiException(
                ErrorCode::ServerError,
                'Razorpay is not configured. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.',
                [],
                500,
            );
        }

        $receipt = (string) ($meta['invoice_number'] ?? ('rcpt_'.Str::random(10)));

        $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
            ->acceptJson()
            ->post(self::API.'/orders', [
                'amount' => $amount->minor,        // paise
                'currency' => $amount->currency,
                'receipt' => $receipt,
                'notes' => [
                    'invoice_number' => $meta['invoice_number'] ?? null,
                    'company_id' => $meta['company_id'] ?? null,
                ],
            ]);

        if ($response->failed()) {
            throw new ApiException(
                ErrorCode::ServerError,
                'Failed to create Razorpay order.',
                ['gateway_status' => $response->status()],
                502,
            );
        }

        $order = $response->json();

        return [
            'gateway' => $this->name(),
            'order_id' => $order['id'] ?? null,
            'key_id' => $this->keyId(),       // public key for the checkout widget
            'amount' => $order['amount'] ?? $amount->minor,
            'currency' => $order['currency'] ?? $amount->currency,
            'receipt' => $receipt,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('billing.gateways.razorpay.webhook_secret', '');
        if ($secret === '') {
            return false; // never accept unsigned webhooks for a real gateway
        }

        $signature = (string) $request->header('X-Razorpay-Signature', '');
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseEvent(array $payload): NormalizedEvent
    {
        $event = (string) ($payload['event'] ?? '');
        $entity = $payload['payload']['payment']['entity'] ?? [];

        $type = match ($event) {
            'payment.captured', 'order.paid' => 'payment.captured',
            'payment.failed' => 'payment.failed',
            'refund.processed' => 'refund.processed',
            default => $event,
        };

        $paymentId = $entity['id'] ?? null;

        return new NormalizedEvent(
            // Razorpay also sends an x-razorpay-event-id header; the payment id +
            // event type is a stable idempotency key for our purposes.
            eventId: (string) ($paymentId ? $paymentId.':'.$event : Str::uuid()->toString()),
            type: $type,
            gatewayPaymentId: $paymentId,
            amountMinor: (int) ($entity['amount'] ?? 0),
            invoiceNumber: $entity['notes']['invoice_number'] ?? null,
            raw: $payload,
        );
    }

    private function keyId(): ?string
    {
        return config('billing.gateways.razorpay.key_id');
    }

    private function keySecret(): ?string
    {
        return config('billing.gateways.razorpay.key_secret');
    }
}
