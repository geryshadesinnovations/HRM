<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Billing\Models\Coupon;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super-admin coupon management (req #3). Coupons are platform-level and can be
 * redeemed by any company at checkout. See docs/04-BILLING.md.
 */
final class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Coupon::orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateCoupon($request, null);
        $data['uuid'] = (string) Str::uuid();
        $coupon = Coupon::create($data);

        return ApiResponse::success($coupon, status: 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($this->validateCoupon($request, $coupon->id));

        return ApiResponse::success($coupon->refresh());
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return ApiResponse::success(['message' => 'Coupon deleted.']);
    }

    private function validateCoupon(Request $request, ?int $couponId): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('coupons', 'code')->ignore($couponId)],
            'description' => ['nullable', 'string', 'max:200'],
            'type' => ['required', Rule::in(Coupon::TYPES)],
            'value' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'plan_code' => ['nullable', 'string', 'max:40', Rule::exists('plans', 'code')],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);
    }
}
