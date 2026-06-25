<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Subscription\Models\Subscription;
use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant invoice. Money in minor units. States: draft|open|paid|void|uncollectible.
 * See docs/04-BILLING.md.
 */
class Invoice extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUSES = ['draft', 'open', 'paid', 'void', 'uncollectible'];

    protected $fillable = [
        'uuid', 'company_id', 'subscription_id', 'number', 'status',
        'subtotal', 'tax_total', 'total', 'currency', 'gstin',
        'issued_at', 'due_at', 'paid_at', 'meta',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'tax_total' => 'integer',
        'total' => 'integer',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
