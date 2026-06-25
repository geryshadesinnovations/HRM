<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw gateway webhook persisted for idempotency + audit. Platform-level (not
 * tenant-scoped): callbacks arrive without a tenant context.
 * Unique on (gateway, event_id) deduplicates retried deliveries.
 *
 * See docs/04-BILLING.md (Webhook Flow).
 */
class WebhookEvent extends Model
{
    protected $fillable = ['gateway', 'event_id', 'type', 'payload', 'processed_at'];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
