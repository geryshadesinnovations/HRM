<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Platform\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A platform-level discount coupon (percent or fixed). Not tenant-scoped —
 * created by the Super Admin and applied to any company's invoice.
 * See docs/04-BILLING.md.
 */
class Coupon extends Model
{
    use HasUuid;
    use SoftDeletes;

    public const TYPES = ['percent', 'fixed'];

    protected $fillable = [
        'uuid', 'code', 'description', 'type', 'value', 'currency',
        'plan_code', 'max_redemptions', 'redemptions_count',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'value' => 'integer',
        'max_redemptions' => 'integer',
        'redemptions_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Whether the coupon is currently redeemable (active, in-window, under cap). */
    public function isRedeemable(?string $planCode = null, ?Carbon $now = null): bool
    {
        $now ??= now();

        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->expires_at !== null && $now->gt($this->expires_at)) {
            return false;
        }
        if ($this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions) {
            return false;
        }
        if ($this->plan_code !== null && $planCode !== null && $this->plan_code !== $planCode) {
            return false;
        }

        return true;
    }

    /** Discount (minor units) for a given subtotal, never exceeding it. */
    public function discountFor(int $subtotalMinor): int
    {
        $discount = $this->type === 'percent'
            ? (int) round($subtotalMinor * $this->value / 100)
            : (int) $this->value;

        return max(0, min($discount, $subtotalMinor));
    }
}
