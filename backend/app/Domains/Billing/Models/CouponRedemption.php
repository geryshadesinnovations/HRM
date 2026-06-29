<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Platform\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records a single redemption of a coupon by a company (audit + cap tracking).
 */
class CouponRedemption extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'coupon_id', 'company_id', 'invoice_id', 'amount_discounted',
    ];

    protected $casts = [
        'amount_discounted' => 'integer',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
