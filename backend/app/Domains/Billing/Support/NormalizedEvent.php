<?php

declare(strict_types=1);

namespace App\Domains\Billing\Support;

/**
 * Canonical, gateway-independent webhook event. Every adapter maps its
 * provider-specific payload into this shape so downstream processing is uniform.
 *
 * Canonical types: payment.captured, payment.failed, refund.processed,
 * subscription.charged, mandate.revoked.
 */
final class NormalizedEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly ?string $gatewayPaymentId,
        public readonly int $amountMinor,
        public readonly ?string $invoiceNumber,
        public readonly array $raw = [],
    ) {}
}
