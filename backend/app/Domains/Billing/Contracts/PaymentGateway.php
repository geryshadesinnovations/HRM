<?php

declare(strict_types=1);

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Support\NormalizedEvent;
use App\Platform\Support\Money;
use Illuminate\Http\Request;

/**
 * Gateway-agnostic payment contract. Adapters (Razorpay/Cashfree/PayU/manual)
 * implement this so business code never depends on a specific provider.
 * The active gateway is resolved by GatewayManager from config.
 *
 * See docs/04-BILLING.md (Gateway Abstraction).
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Whether this adapter has the credentials it needs to talk to the provider.
     * Used by GatewayManager to fall back to the manual adapter in dev/unconfigured
     * environments so checkout keeps working.
     */
    public function isConfigured(): bool;

    /**
     * Create a payment order/intent for an amount.
     *
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed> gateway order reference (id, checkout params)
     */
    public function createOrder(Money $amount, array $meta = []): array;

    /** Verify a webhook's authenticity (signature/secret). */
    public function verifyWebhook(Request $request): bool;

    /** Map a verified raw webhook payload to a canonical event. */
    public function parseEvent(array $payload): NormalizedEvent;
}
