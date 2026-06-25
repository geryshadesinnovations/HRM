<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Platform\Concerns\HasUuid;
use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment against an invoice. States: created|authorized|captured|failed|refunded.
 */
class Payment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'uuid', 'company_id', 'invoice_id', 'gateway', 'gateway_payment_id',
        'status', 'amount', 'currency', 'raw_payload',
    ];

    protected $casts = [
        'amount' => 'integer',
        'raw_payload' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
