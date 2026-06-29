<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Coupon;
use App\Domains\Billing\Models\CouponRedemption;
use App\Domains\Billing\Models\Invoice;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Validates and redeems discount coupons. Coupons are platform-level; a company
 * may use a given coupon only once. See docs/04-BILLING.md.
 */
final class CouponService
{
    /**
     * Look up an active, redeemable coupon by code or throw a 422.
     */
    public function validate(string $code, ?string $planCode = null, ?int $companyId = null): Coupon
    {
        $coupon = Coupon::where('code', $code)->first();

        if ($coupon === null || ! $coupon->isRedeemable($planCode)) {
            throw new ApiException(ErrorCode::ValidationFailed, 'This coupon is not valid.', ['code' => $code], 422);
        }

        if ($companyId !== null && $this->alreadyRedeemed($coupon, $companyId)) {
            throw new ApiException(ErrorCode::ValidationFailed, 'This coupon has already been used by your company.', [], 422);
        }

        return $coupon;
    }

    public function alreadyRedeemed(Coupon $coupon, int $companyId): bool
    {
        return CouponRedemption::where('coupon_id', $coupon->id)
            ->where('company_id', $companyId)
            ->exists();
    }

    /**
     * Record a redemption and increment the coupon's counter atomically.
     */
    public function redeem(Coupon $coupon, int $companyId, Invoice $invoice, int $amountDiscounted): CouponRedemption
    {
        return DB::transaction(function () use ($coupon, $companyId, $invoice, $amountDiscounted) {
            $coupon->increment('redemptions_count');

            return CouponRedemption::create([
                'uuid' => (string) Str::uuid(),
                'coupon_id' => $coupon->id,
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'amount_discounted' => $amountDiscounted,
            ]);
        });
    }
}
