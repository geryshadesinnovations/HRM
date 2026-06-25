<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Gateways\ManualGateway;
use App\Domains\Billing\Gateways\RazorpayGateway;
use InvalidArgumentException;

/**
 * Resolves the active payment gateway from config (billing.default_gateway).
 * Switching providers is a configuration change, not a code change.
 *
 * Register real adapters here as they are implemented (razorpay/cashfree/payu).
 * See docs/04-BILLING.md.
 */
final class GatewayManager
{
    /** @var array<string,class-string<PaymentGateway>> */
    private array $adapters = [
        'manual' => ManualGateway::class,
        'razorpay' => RazorpayGateway::class,
        // 'cashfree' => CashfreeGateway::class,
        // 'payu'     => PayuGateway::class,
    ];

    /**
     * Resolve a gateway for creating orders. Falls back to the manual adapter
     * when the requested provider is unknown OR not yet configured with keys,
     * so checkout keeps working in dev/unconfigured environments.
     */
    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= (string) config('billing.default_gateway', 'manual');

        $class = $this->adapters[$name] ?? null;

        if ($class === null) {
            return app(ManualGateway::class);
        }

        $gateway = app($class);

        return $gateway->isConfigured() ? $gateway : app(ManualGateway::class);
    }

    /**
     * Resolve the exact adapter for a webhook (no fallback): the signature must
     * be verified with the provider that actually sent the event.
     */
    public function forWebhook(string $gateway): PaymentGateway
    {
        $class = $this->adapters[$gateway] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No payment gateway registered for '{$gateway}'.");
        }

        return app($class);
    }
}
