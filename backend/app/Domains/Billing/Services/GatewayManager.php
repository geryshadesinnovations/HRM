<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Gateways\ManualGateway;
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
        // 'razorpay' => RazorpayGateway::class,
        // 'cashfree' => CashfreeGateway::class,
        // 'payu'     => PayuGateway::class,
    ];

    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= (string) config('billing.default_gateway', 'manual');

        // Fall back to the manual adapter for any provider not yet implemented,
        // so the platform stays functional while real adapters are added.
        $class = $this->adapters[$name] ?? $this->adapters['manual'] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No payment gateway registered for '{$name}'.");
        }

        return app($class);
    }

    public function forWebhook(string $gateway): PaymentGateway
    {
        return $this->gateway($gateway);
    }
}
