<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A single invoice line. `line_total = quantity * unit_amount` (pre-tax).
 */
class InvoiceLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'invoice_id', 'description', 'quantity',
        'unit_amount', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_amount' => 'integer',
        'tax_rate' => 'float',
        'line_total' => 'integer',
    ];
}
